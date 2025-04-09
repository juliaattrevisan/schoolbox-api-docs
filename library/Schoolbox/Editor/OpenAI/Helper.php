<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;

class Helper
{
    /**
     * There are apparently two different endpoint flavours that we can chooze
     */
    private const ENDPOINT_TYPE_OPENAI = 'OpenAI';
    private const ENDPOINT_HOST_OPENAI = 'https://api.openai.com/v1/chat/completions';

    private const ENDPOINT_TYPE_AZURE = 'Azure';
    private const ENDPOINT_HOST_AZURE = 'https://%s.openai.azure.com/openai/deployments/%s/chat/completions?api-version=2023-12-01-preview';


    /**
     * Controller constructor.
     */
    public function __construct(
        private string $openAiEndpoint,
        private string $openAiKey,
        private string $openAiAzureResourceName,
        private string $openAiAzureDeploymentId,
        private string $openAiModel,
        private Client $client,
        private \Schoolbox\Error\Mapper $errorMapper,
        private Log\Mapper $logMapper,
    ) {
    }


    /**
     * request (no streaming, returns full json payload)
     * hits openAI and returns the string response (you need to json decode yourself...)
     *
     * @param User $user // user to log the request against
     * @param array|null $prompt // if generating a payload, use this prompt
     * @param string|null $payload // use this payload instead of generating one
     * @return string|null
     * @throws ClientExceptionInterface
     * @throws Exception
     * @throws \JsonException
     */
    public function request(User $user, ?array $prompt = null, ?string $payload = null): ?string
    {
        /* Assert that we're configured correctly
         */
        if (!$this->openAiKey) {
            throw new \DomainException('OpenAI API key is unavailable');
        }

        // either build or use the supplied payload
        $payload = $payload ?? (new Payload\LessonPlan($this->openAiModel))->generate(
            $prompt['system'],
            $prompt['user'],
        );

        try {
            $openAiRequest = new Request('POST', $this->getEndpoint(), array_merge(
                $this->getAuthorization(),
                ['Content-Type' => 'application/json'],
            ), $payload);

            $response = $this->client->sendRequest($openAiRequest);

            http_response_code($response->getStatusCode());

            /* Log some error messages, if applicable
             */
            $errorMessage = '';

            if ($response->getStatusCode() !== 200) {
                $errorMessage = $response->getReasonPhrase();
            }

            /* Log what the user tried to do if we made a vaguely-successful request
             * and didn't somehow die long the way
             */
            $log = $this->logMapper->createModel()
                ->setUser($user)
                ->setPrompt($payload)
                ->setReturnCode($response->getStatusCode())
                ->setErrorMessage($errorMessage);

            $this->logMapper->save($log);

            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            $this->errorMapper->generateException(ErrorMapper::ERROR_DATABASE_UNAVAILABLE);
        }

        return null;
    }


    /**
     * Asynchronously sends a request to the OpenAI API and returns the response to the user.
     * then die()
     *
     * This is used by Little Robot Dude in the ckeditor ai assistant plugin thing
     * Any deviation from this causes it to stop working for one reason or another
     *
     * @param User $user // user to log the request against
     * @param string|null $prompt // if generating a payload, use this prompt
     * @param string|null $payload // use this payload instead of generating one
     * @return void
     * @throws \JsonException
     * @throws Exception
     */
    public function littleRobotDude(User $user, ?string $prompt = null, ?string $payload = null): void
    {
        /* Assert that we're configured correctly
         */
        if (!$this->openAiKey) {
            throw new \DomainException('OpenAI API key is unavailable');
        }

        // either build or use the supplied payload
        $payload = $payload ?? (new Payload\LittleRobotDude($this->openAiModel))->generate($prompt, stream: true);

        try {
            $openAiRequest = new Request('POST', $this->getEndpoint(), array_merge(
                $this->getAuthorization(),
                ['Content-Type' => 'application/json'],
            ), $payload);

            /* Log what the user tried to do if we made a vaguely-successful request
             * and didn't somehow die long the way
             */
            $log = $this->logMapper->createModel()
                ->setUser($user)
                ->setPrompt($payload);

            $promise = $this->client->sendAsync($openAiRequest, ['stream' => true]);

            // Handle the response when it arrives
            /* The two anonymous functions represent what happens when we succeed
             * and when we didn't.
             */
            $promise->then(
                function ($response) use ($log): void {
                    /* Set up presumed success conditions
                     */
                    $returnCode = 200;

                    http_response_code($returnCode);

                    $log->setReturnCode($returnCode)
                        ->setErrorMessage('');

                    /* Remove preformatted insanity by encoding it all away
                     * https://stackoverflow.com/questions/23859713/remove-all-unnecessary-whitespaces-from-json-string-in-php
                     */
                    //header('X-Accel-Buffering: no');
                    header('Content-Type: application/json');
                    header('Content-Encoding: none');

                    ob_implicit_flush(true);
                    ob_end_flush();

                    $stream = $response->getBody();

                    while (!$stream->eof()) {
                        echo $stream->read(1024);
                    }
                },
                function ($reason) use ($log): void {
                    $returnCode = $reason->getCode();

                    /* @var ClientException $reason */
                    http_response_code($returnCode);

                    /* FIXME This probably needs to be in a standardised json error struct
                     *  The last time we did this was in profile editor
                     */
                    echo $reason->getMessage();

                    /* We died; log the status code and epitaph
                     */
                    $log->setReturnCode($returnCode)
                        ->setErrorMessage($reason->getMessage());
                }
            );

            $promise->wait();

            $this->logMapper->save($log);

            /* The async method will still continue trying to render other aura stuff,
             * like the chrome etc. so people left a die(); here to forcibly terminate;
             * else you end up with a bunch of Warnings about headers already being sent
             *
             * You can dodge that using responseAsJson() instead
             */
            die();
        } catch (GuzzleException $e) {
            $this->errorMapper->generateException(ErrorMapper::ERROR_DATABASE_UNAVAILABLE);
        }
    }


    /** ===================================================== */

    /**
     * Creates an endpoint URL string for the user selected endpoint.  They can choose between OpenAI and Azure.
     *
     */
    private function getEndpoint(): string
    {
        switch ($this->openAiEndpoint) {
            case self::ENDPOINT_TYPE_OPENAI:
                return self::ENDPOINT_HOST_OPENAI;
            case self::ENDPOINT_TYPE_AZURE:
                if (empty($this->openAiAzureResourceName) || empty($this->openAiAzureDeploymentId)) {
                    throw new \UnexpectedValueException('Azure resource name and deployment ID are required');
                }

                return sprintf(
                    self::ENDPOINT_HOST_AZURE,
                    $this->openAiAzureResourceName,
                    $this->openAiAzureDeploymentId,
                );
        }
        throw new \UnexpectedValueException('Endpoint must be configured');
    }

    /**
     * Generates the Auth header for the OpenAI API request.
     *
     * @return array|string[]
     */
    private function getAuthorization(): array
    {
        switch ($this->openAiEndpoint) {
            case self::ENDPOINT_TYPE_OPENAI:
                return ['Authorization' => 'Bearer ' . $this->openAiKey];
            case self::ENDPOINT_TYPE_AZURE:
                return ['api-key' => $this->openAiKey];
        }

        throw new \UnexpectedValueException('Endpoint must be configured');
    }
}

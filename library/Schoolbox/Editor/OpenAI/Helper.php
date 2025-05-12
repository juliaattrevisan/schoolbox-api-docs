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
     *
     * The CHAT endpoint is for Little Robot Dude, a conversational generator.
     * The REQUEST endpoint is for everything else, obeys structured output
     */
    public const ENDPOINT_TYPE_OPENAI = 'OpenAI';
    private const ENDPOINT_HOST_OPENAI_CHAT = 'https://api.openai.com/v1/chat/completions';
    private const ENDPOINT_HOST_OPENAI_REQUEST = 'https://api.openai.com/v1/responses';

    public const ENDPOINT_TYPE_AZURE = 'Azure';
    private const ENDPOINT_HOST_AZURE_CHAT = 'https://%s.openai.azure.com/openai/deployments/%s/chat/completions?api-version=2023-12-01-preview';
    private const ENDPOINT_HOST_AZURE_REQUEST = 'https://%s.openai.azure.com/openai/responses?api-version=2025-03-01-preview';


    /**
     * Structured Outputs is only supported in certain models
     * Note that we intentionally don't support gippity 3.5
     */
    private const SUPPORTED_STRUCTURED_OUTPUT_MODELS = [
        self::ENDPOINT_TYPE_OPENAI => [
//            'gpt-3.5-turbo' => false,
//            'gpt-4' => false,
            'gpt-4o' => true,
            'gpt-4o-mini' => true,
            'gpt-4.1' => true,
            'gpt-4.1-mini' => true,
//            'gpt-4-turbo' => false
        ],
        self::ENDPOINT_TYPE_AZURE => [
//            'gpt-3.5-turbo' => false,
//            'gpt-4' => false,
            'gpt-4o' => true,
            'gpt-4o-mini' => true,
            'gpt-4.1' => true,
            'gpt-4.1-mini' => true,
//            'gpt-4-turbo' => false
        ],
    ];


    /**
     * Use the conversational chatgippity variant
     */
    private const CHAT = 'chat';


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
     * @param string $payload // use this payload instead of generating one
     * @return array(string|null, Log\Model)
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function request(User $user, string $payload): array
    {
        /* Assert that we're configured correctly
         */
        if (!$this->openAiKey) {
            throw new \DomainException('OpenAI API key is unavailable');
        }

        /* Requires a particular minimum set of models to continue.
         */
        if (!$this->canUseStructuredOutputs()) {
            throw new \DomainException('AI endpoint/model does not support Structured Outputs');
        }

        /* Minimum requirements met: let's send it and see what AI overlords think of our payload
         */
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
                ->setModel($this->openAiModel)
                ->setReturnCode($response->getStatusCode())
                ->setErrorMessage($errorMessage);

            $this->logMapper->save($log);

            /* The async method will still continue trying to render other aura stuff,
             * like the chrome etc. so people left a die(); here to forcibly terminate;
             * else you end up with a bunch of Warnings about headers already being sent
             *
             * You can dodge that using responseAsJson() instead
             */
            return [$response->getBody()->getContents(), $log];
        } catch (GuzzleException $e) {
            $this->errorMapper->generateException(ErrorMapper::ERROR_DATABASE_UNAVAILABLE);
        }

        return [null, $log];
    }


    /**
     * Structured Outputs require a particular model
     *
     * @return bool
     */
    private function canUseStructuredOutputs(): bool
    {
        return self::SUPPORTED_STRUCTURED_OUTPUT_MODELS[$this->openAiEndpoint][$this->openAiModel] ?? false;
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
            $openAiRequest = new Request('POST', $this->getEndpoint(self::CHAT), array_merge(
                $this->getAuthorization(),
                ['Content-Type' => 'application/json'],
            ), $payload);

            /* Log what the user tried to do if we made a vaguely-successful request
             * and didn't somehow die long the way
             */
            $log = $this->logMapper->createModel()
                ->setUser($user)
                ->setPrompt($payload)
                ->setModel($this->openAiModel);

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

                    // store a section of the response (end of the stream)
                    $output = '';

                    while (!$stream->eof()) {
                        $output = $stream->read(1024);
                        echo $output;
                    }

                    //store the last of the output and the model (if it exists
                    $log->setResponse($output);
                    $log->setModel($this->extractModel($output));
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

            /* Intentionally die
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
    private function getEndpoint($type = null): string
    {
        switch ($this->openAiEndpoint) {
            case self::ENDPOINT_TYPE_OPENAI:
                /* Little Robot Dude
                 */
                if (self::CHAT === $type) {
                    return self::ENDPOINT_HOST_OPENAI_CHAT;
                }

                /* This is for structured outputs (used in lesson plans)
                 */
                return self::ENDPOINT_HOST_OPENAI_REQUEST;
            case self::ENDPOINT_TYPE_AZURE:
                if (empty($this->openAiAzureResourceName) || empty($this->openAiAzureDeploymentId)) {
                    throw new \UnexpectedValueException('Azure resource name and deployment ID are required');
                }

                /* Little Robot Dude
                 */
                if (self::CHAT === $type) {
                    return sprintf(
                        self::ENDPOINT_HOST_AZURE_CHAT,
                        $this->openAiAzureResourceName,
                        $this->openAiAzureDeploymentId,
                    );
                }

                /* This is for structured outputs (used in lesson plans)
                 *
                 * MAYBE Azure does not support structured outputs at the moment
                 * https://learn.microsoft.com/en-us/azure/ai-services/openai/how-to/responses?tabs=python-secure
                 *
                    Not currently supported:

                    Structured outputs
                    tool_choice
                    image_url pointing to an internet address
                    The web search tool is also not supported, and is not part of the 2025-03-01-preview API.
                 */
                return sprintf(
                    self::ENDPOINT_HOST_AZURE_REQUEST,
                    $this->openAiAzureResourceName,
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

    /**
     * Little Robot Dude:
     * Extracts the model from the OpenAI API response payload.
     *
     * @param string $payload The response payload from the OpenAI API.
     * @return string|null The extracted model or null if not found.
     */
    private function extractModel($payload)
    {
        $payload = explode("\n", $payload);

        foreach ($payload as $line) {
            if (str_starts_with($line, 'data:')) {
                $json = json_decode(substr($line, 5), true);
                if (isset($json['model'])) {
                    return $json['model'];
                }
            }
        }

        return null;
    }
}

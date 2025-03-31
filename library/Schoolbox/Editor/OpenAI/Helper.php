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
    private const OPENAI_API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const AZURE_API_ENDPOINT = 'https://%s.openai.azure.com/openai/deployments/%s/chat/completions?api-version=2023-12-01-preview';


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
     * chat (no async)
     * hits openAI and returns the string response (you need to json decode yourself...)
     *
     * @param User $user // user to log the request against
     * @param string|null $prompt // if generating a payload, use this prompt
     * @param string|null $payload // use this payload instead of generating one
     * @param array|null $responseFormat // null, or array of response format
     * @return string|null
     * @throws Exception
     * @throws \JsonException
     * @throws ClientExceptionInterface
     */
    public function chat(User $user, ?string $prompt = null, ?string $payload = null, ?array $responseFormat = null): ?string
    {
        $log = $this->logMapper->createModel();
        $log->setUser($user);

        // either build or use the supplied payload
        $payload = $payload ?? $this->generatePayload($prompt, $responseFormat);

        try {
            $openAiRequest = new Request('POST', $this->getEndpoint(), array_merge(
                $this->getAuthorization(),
                ['Content-Type'  => 'application/json'],
            ), $payload);
            $log->setPrompt($payload);

            $response = $this->client->sendRequest($openAiRequest);

            http_response_code($response->getStatusCode());

            $log->setReturnCode($response->getStatusCode());
            if ($response->getStatusCode() !== 200) {
                $log->setErrorMessage($response->getReasonPhrase());
            } else {
                $log->setErrorMessage('');
            }

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
     * @param User $user // user to log the request against
     * @param string|null $prompt // if generating a payload, use this prompt
     * @param string|null $payload // use this payload instead of generating one
     * @return void
     * @throws \JsonException
     * @throws Exception
     */
    public function async(User $user, ?string $prompt = null, ?string $payload = null): void
    {
        $log = $this->logMapper->createModel();
        $log->setUser($user);

        // either build or use the supplied payload
        $payload = $payload ?? $this->generatePayload($prompt);

        try {
            $openAiRequest = new Request('POST', $this->getEndpoint(), array_merge(
                $this->getAuthorization(),
                ['Content-Type'  => 'application/json'],
            ), $payload);
            $log->setPrompt($payload);

            $promise = $this->client->sendAsync($openAiRequest, ['stream' => true]);

            // Handle the response when it arrives
            $promise->then(
                function ($response) use ($log): void {
                    http_response_code(200);
                    $log->setReturnCode(200);
                    $log->setErrorMessage('');

                    //header('X-Accel-Buffering: no');
                    header('Content-Type: application/json');
                    header('Content-Encoding: none');

                    ob_implicit_flush(true);
                    ob_end_flush();

                    $stream = $response->getBody();

                    // Read and proxy the data
                    while (!$stream->eof()) {
                        echo $stream->read(1024);
                        //ob_flush();
                        //flush();
                    }
                },
                function ($reason) use ($log): void {
                    /* @var ClientException $reason */
                    http_response_code($reason->getCode());
                    echo $reason->getMessage();

                    $log->setReturnCode($reason->getCode());
                    $log->setErrorMessage($reason->getMessage());
                }
            );

            $promise->wait();
        } catch (GuzzleException $e) {
            $this->errorMapper->generateException(ErrorMapper::ERROR_DATABASE_UNAVAILABLE);
        }

        $this->logMapper->save($log);
    }


    /** ===================================================== */

    /**
     * Creates an endpoint URL string for the user selected endpoint.  They can choose between OpenAI and Azure.
     *
     */
    private function getEndpoint(): string
    {
        switch ($this->openAiEndpoint) {
            case 'OpenAI':
                return self::OPENAI_API_ENDPOINT;
            case 'Azure':
                if (empty($this->openAiAzureResourceName) || empty($this->openAiAzureDeploymentId)) {
                    throw new \UnexpectedValueException('Azure resource name and deployment ID are required');
                }

                return sprintf(
                    self::AZURE_API_ENDPOINT,
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
            case 'OpenAI':
                return ['Authorization' => 'Bearer ' . $this->openAiKey];
            case 'Azure':
                return ['api-key' => $this->openAiKey];
        }

        throw new \UnexpectedValueException('Endpoint must be configured');
    }

    /**
     * Generates the payload for the OpenAI API request.
     *
     * @param string $prompt // user prompt to send to the API
     * @param ?array $responseFormat
     * @param bool $stream
     * @return string
     * @throws \JsonException
     */
    private function generatePayload(string $prompt, ?array $responseFormat = null, bool $stream = false): string
    {
        $payload = [
            "messages" => [
                [
                    "role" => "system",
                    "content" => "Your task is to execute the instruction using the provided HTML content. Follow the instruction closely. Your answer must be a properly formatted HTML code. Do not add any additional remarks or notes. Do not act like a chatbot or a real person.",
                ],
                [
                    "role" => "user",
                    "content" => $prompt
                ],
            ],
            "model" => $this->openAiModel,
            "max_tokens" => 2000,
            "temperature" => 1,
            "top_p" => 1,
            "stream" => $stream
        ];

        // only format if requested
        if ($responseFormat) {
            $payload['response_format'] = ["type" => "json_object"];
            $payload['functions'] = [$responseFormat];
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}

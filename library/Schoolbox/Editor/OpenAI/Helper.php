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
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\User;

class Helper
{
    private const OPENAI_API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const AZURE_API_ENDPOINT = 'https://%s.openai.azure.com/openai/deployments/%s/chat/completions?api-version=2023-12-01-preview';

    private ErrorMapper $errorMapper;
    /**
     * Controller constructor.
     */
    public function __construct(private Client $client, private Log\Mapper $logMapper, private IConfigLoader $config)
    {
        $this->errorMapper = ErrorMapper::GetInstance();
    }

    /**
     * chat (no async)
     */
    public function chat(string $prompt, User $user, string $format)
    {
        /**
         * @var Log\Model $log
         */
        $log = $this->logMapper->createModel();
        $log->setUser($user);

        $query = $this->generatePayload($prompt, $format);

        try {
            $openAiRequest = new Request('POST', $this->getEndpoint(), array_merge(
                $this->getAuthorization(),
                ['Content-Type'  => 'application/json'],
            ), $query);
            $log->setPrompt($query);

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
     *
     * @param string $prompt
     * @param User $user
     * @return void
     * @throws \Schoolbox\Mapper\Exception
     */
    public function async(User $user, ?string $prompt = null, ?string $payload = null): void
    {
        /**
         * @var Log\Model $log
         */
        $log = $this->logMapper->createModel();
        $log->setUser($user);

        // either build or use the supplied payload
        $payload = $payload ?? $this->generatePayload($prompt, 'string');

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

        die();
    }


    /** ===================================================== */

    /**
     * Creates an endpoint URL string for the user selected endpoint.  They can choose between OpenAI and Azure.
     *
     */
    private function getEndpoint(): string
    {
        switch ($this->config->get('openai_endpoint')) {
            case 'OpenAI':
                return self::OPENAI_API_ENDPOINT;
            case 'Azure':
                if (empty($this->config->get('openai_azure_resource_name')) || empty($this->config->get('openai_azure_deployment_id'))) {
                    throw new \UnexpectedValueException('Azure resource name and deployment ID are required');
                }

                return sprintf(
                    self::AZURE_API_ENDPOINT,
                    $this->config->get('openai_azure_resource_name'),
                    $this->config->get('openai_azure_deployment_id')
                );
        }
        throw new \UnexpectedValueException('Endpoint must be configured');
    }

    private function getAuthorization(): array
    {
        switch ($this->config->get('openai_endpoint')) {
            case 'OpenAI':
                return ['Authorization' => 'Bearer ' . $this->config->get('openai_key')];
            case 'Azure':
                return ['api-key' => $this->config->get('openai_key')];
        }

        throw new \UnexpectedValueException('Endpoint must be configured');
    }

    /**
     * Generates the payload for the OpenAI API request.
     *
     * @param string $prompt // user prompt to send to the API
     * @param string $format
     * @param bool $stream
     * @return string
     * @throws \JsonException
     */
    private function generatePayload(string $prompt, string $format, bool $stream = false): string
    {
        return json_encode([
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
            "model" => $this->config->get('openai_model'),
            "max_tokens" => 2000,
            "temperature" => 1,
            "top_p" => 1,
            "stream" => $stream

        ], JSON_THROW_ON_ERROR);
    }
}

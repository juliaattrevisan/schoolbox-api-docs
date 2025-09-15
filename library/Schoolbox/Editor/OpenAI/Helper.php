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
use Schoolbox\Application\Exception\Exception as SchoolboxException;
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
     * This is the value of text and image endpoint providers if a school
     * turned off these features.
     */
    public const ENDPOINT_TYPE_OFF = 'Off';

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
     * Feature types (both for logging and identifying in schoolbox code)
     */
    public const FEATURE_CHAT = 'chat';
    public const FEATURE_LESSONPLAN = 'lessonplan';
    public const FEATURE_IMAGE = 'image';


    /**
     * When an image is omitted from the request/response, we replace the base64 data with this const
     */
    private const IMAGE_DATA_OMITTED = 'omitted';


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
     * Retrieve the model to use in requests
     * OpenAI lets us choose the model (from config),
     * Azure has the model linked to the deployment ID when you set it up over there, so we can't choose it
     */
    public function getModel(): string
    {
        if (Helper::ENDPOINT_TYPE_AZURE === $this->openAiEndpoint) {
            return $this->openAiAzureDeploymentId;
        }

        return $this->openAiModel;
    }


    /**
     * Parse a chunk received from the SSE stream
     * The 'output' key is the original chunk data excluding large base64 image data to avoid it ending up in the log
     *
     * @param string $chunk The raw chunk data from the stream
     * @return array{type:string|null,data:array,output:string}
     */
    private function parseChunk(string $chunk): array
    {
        $type = null;
        $data = [];
        $lines = explode("\n", $chunk);
        $output = '';
        foreach ($lines as $line) {
            if (str_starts_with($line, 'event: ')) {
                $type = trim(substr($line, 7));
                $output .= "{$line}\n";
            } elseif (str_starts_with($line, 'data: ')) {
                try {
                    $item = json_decode(trim(substr($line, 6)), true, 512, JSON_THROW_ON_ERROR);
                    $data[] = $item;

                    // Omit the actual image data from the output
                    $itemType = $item['type'] ?? null;
                    if ($itemType === 'response.image_generation_call.partial_image' && isset($item['partial_image_b64'])) {
                        $item['partial_image_b64'] = self::IMAGE_DATA_OMITTED;
                    }

                    // remove the completed image
                    if ($itemType === 'response.output_item.done' && isset($item['item']['result'])) {
                        $item['item']['result'] = self::IMAGE_DATA_OMITTED;
                    }

                    // apparently there is a final final image, lets remove that also
                    if ($itemType === 'response.completed' && isset($item['response']['output']) && is_array($item['response']['output'])) {
                        // $item['response']['output'] is apparently an array, and we need to ommit the b64 from each $item['response']['output'][]['result']
                        foreach ($item['response']['output'] as $i => $respOutput) {
                            if (isset($respOutput['result'])) {
                                $item['response']['output'][$i]['result'] = self::IMAGE_DATA_OMITTED;
                            }
                        }
                    }

                    $output .= sprintf("data: %s\n", json_encode($item, JSON_THROW_ON_ERROR));
                } catch (\JsonException $e) {
                    // If it's not JSON, still save the raw data
                    $output .= "{$line}\n";
                }
            }
        }

        return [
            'type' => $type,
            'data' => $data,
            'output' => $output,
        ];
    }


    /**
     * Append a chunk's output / metrics to the log
     *
     * @param array $chunkData - from parseChunk()
     * @param Log\Model $log
     * @param ?string $type
     */
    private function appendChunkToLog(array $chunkData, Log\Model $log, ?string $type = null): void
    {
        $log->setResponse(($log->getResponse() ?? '') . $chunkData['output']);

        foreach ($chunkData['data'] as $item) {
            $model = null;
            $inputTokens = null;
            $outputTokens = null;

            if ($type === self::FEATURE_CHAT) {
                $model = $item['model'] ?? null;
                $inputTokens = $item['usage']['prompt_tokens'] ?? null;
                $outputTokens = $item['usage']['completion_tokens'] ?? null;
            } else {
                $model = $item['response']['model'] ?? null;
                $inputTokens = $item['response']['usage']['input_tokens'] ?? null;
                $outputTokens = $item['response']['usage']['output_tokens'] ?? null;
            }

            if ($model) {
                $log->setModel($model);
            }
            if ($inputTokens) {
                $log->setInputTokens($inputTokens);
            }
            if ($outputTokens) {
                $log->setOutputTokens($outputTokens);
            }
        }
    }


    /**
     * Read a chunked stream response from OpenAI and echo it once each full chunk is received
     *
     * @param \Psr\Http\Message\StreamInterface $stream
     * @param Log\Model $log
     * @param ?string $type
     * @return ?array The final "response.completed" chunk, or null if it was never found
     */
    public function readStream(\Psr\Http\Message\StreamInterface $stream, Log\Model $log, ?string $type = null): ?array
    {
        $chunkBuffer = '';
        $isGeneratingImage = false;
        $completedEvent = null;

        while (!$stream->eof()) {
            // Read larger chunks if in the middle of generating an image (as we're about to recieve a lot of data)
            $readSize = $isGeneratingImage ? 8192 : 128;
            $data = $stream->read($readSize);
            if ('' === $data) {
                usleep(100000);
                continue;
            }

            // Normalise CRLF to LF
            if (false !== strpos($data, "\r")) {
                $data = strtr($data, ["\r\n" => "\n", "\r" => "\n"]);
            }

            $chunkBuffer .= $data;

            // while there's at least one full chunk in the buffer, process it
            while (false !== ($pos = strpos($chunkBuffer, "\n\n"))) {
                $chunk = substr($chunkBuffer, 0, $pos);
                $chunkBuffer = substr($chunkBuffer, $pos + 2);

                $event = $this->parseChunk($chunk);
                $this->appendChunkToLog($event, $log, $type);

                // Don't echo an 'error' chunk if this isn't going to ckeditor as it won't follow the format of an ApiProblem
                if ($type === self::FEATURE_CHAT || $event['type'] !== 'error') {
                    echo "{$chunk}\n\n";
                }

                // Is this the final event?
                // There are 3 possible events that indicate the end of a response (see https://platform.openai.com/docs/api-reference/responses-streaming/response)
                if (in_array($event['type'], ['response.completed', 'response.failed', 'response.incomplete'])) {
                    $completedEvent = $event['data'][0]['response'];
                    // Keep reading the stream as there *might* be more later that we can still log to debug issues
                }

                // Keep track of whether we're generating an image
                if ($event['type'] === 'response.image_generation_call.generating') {
                    $isGeneratingImage = true;
                } else if ($event['type'] === 'response.image_generation_call.completed') {
                    $isGeneratingImage = false;
                }

                // flush PHP buffers
                if (ob_get_length()) {
                    @ob_flush();
                }
                @flush();
            }
        }

        // If we have any remaining data in the buffer, process it
        if (strlen(trim($chunkBuffer)) > 0) {
            echo $chunkBuffer;
            $event = $this->parseChunk($chunkBuffer);
            $this->appendChunkToLog($event, $log, $type);

            if ($event['type'] === 'response.completed') {
                $completedEvent = $event['data'][0]['response'];
            }
        }

        return $completedEvent;
    }


    /**
     * Request hits OpenAI and returns the parsed response data
     * Uses streaming if the payload has 'stream' set to true
     * Will return the final json response for both streaming and non-streaming requests
     *
     * @param User $user // user to log the request against
     * @param string $payload // use this payload instead of generating one
     * @param string $feature // frontend schoolbox ai feature type for logging (lessonplan, image, etc.)
     * @return array
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function request(User $user, string $payload, string $feature): array
    {
        /* Check that we have activated this endpoint
         */
        if (self::ENDPOINT_TYPE_OFF === $this->openAiEndpoint) {
            throw new \DomainException('OpenAI request queries are disabled. Choose an endpoint provider.');
        }

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
        /* Log what the user tried to do if we made a vaguely-successful request
         * and didn't somehow die long the way
         */
        $log = $this->logMapper->createModel()
            ->setUser($user)
            ->setPrompt($payload)
            ->setFeature($feature)
            ->setEndpoint($this->getEndpoint());

        /* Minimum requirements met: let's send it and see what AI overlords think of our payload
         */
        try {
            // Is streaming enabled?
            $stream = json_decode($payload, true, 512, JSON_THROW_ON_ERROR)['stream'] ?? false;

            $openAiRequest = new Request('POST', $this->getEndpoint(), array_merge(
                $this->getAuthorization(),
                ['Content-Type' => 'application/json'],
            ), $payload);

            $response = $this->client->send($openAiRequest, [
                \GuzzleHttp\RequestOptions::ALLOW_REDIRECTS => false,
                \GuzzleHttp\RequestOptions::HTTP_ERRORS => false,
                \GuzzleHttp\RequestOptions::STREAM => $stream,
            ]);

            /* We'll either recieve a text/event-stream or json response depending on if it errored
             * If it is a text/event-stream, handle as so - otherwise assume json
             */
            if (strpos($response->getHeaderLine('Content-Type'), 'text/event-stream') !== false) {
                http_response_code($response->getStatusCode());
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('Content-Encoding: none');

                ob_implicit_flush(true);
                ob_end_flush();

                $stream = $response->getBody();
                $data = $this->readStream($stream, $log);
            } else {
                $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (GuzzleException $e) {
            $log->setReturnCode($e->getCode())
                ->setErrorMessage($e->getMessage());
            $this->logMapper->save($log);
            throw new SchoolboxException(
                "Error requesting data from the AI service",
                500,
                $e
            );
        } catch (\JsonException $e) {
            $log->setReturnCode($e->getCode())
                ->setErrorMessage(sprintf("JsonError: %s", $e->getMessage()));
            $this->logMapper->save($log);
            throw new SchoolboxException(
                "Error decoding response from the AI service",
                500,
                $e
            );
        }

        /* If something went wrong on the openAI side, it'll send us an "error" key
         * https://platform.openai.com/docs/api-reference/responses/object#responses/object-error
         * If we get this, send on the human-readable message to the user
         */
        if ($data["error"] ?? null) {
            $returnCode = 500;

            $error_message = $data["error"]["message"] ?? '';

            $log->setReturnCode($returnCode)
                ->setErrorMessage($error_message);
            $this->logMapper->save($log);

            throw new SchoolboxException(
                $error_message,
                $returnCode
            );
        }

        /* Did we recieve an incomplete response? i.e. max output tokens reached?
         * If so, send those details on so we have some idea of why it failed
         * https://platform.openai.com/docs/api-reference/responses/object#responses/object-status
         */
        $status = $data["status"] ?? null;
        if ($status !== "completed") {
            $returnCode = 500;

            $title = sprintf(
                "Failed to generate a response: %s",
                $status ?? "unknown"
            );

            /* there may be some extra details about why it was incomplete */
            $incompleteMessage = $data["incomplete_details"]["reason"] ?? null;
            if ($incompleteMessage) {
                $title .= sprintf(" (%s)", $incompleteMessage);
            }

            $log->setReturnCode($returnCode)
                ->setErrorMessage($incompleteMessage);
            $this->logMapper->save($log);

            throw new SchoolboxException(
                $title,
                $returnCode
            );
        }

        // well we didn't die, so let's assume we got a good response

        /** store the response, and the ai usage */
        $log->setReturnCode(200)
            ->setModel($data['model'] ?? null)
            ->setInputTokens($data['usage']['input_tokens'] ?? null)
            ->setOutputTokens($data['usage']['output_tokens'] ?? null);
        $this->logMapper->save($log);

        return $data;
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
     * Asynchronously sends a request to the OpenAI API Chat and returns the response to the user.
     * then die()
     *
     * This is used by Little Robot Dude in the ckeditor ai assistant plugin thing
     * Any deviation from this causes it to stop working for one reason or another
     *
     * @param User $user // user to log the request against
     * @param string $payload // the fully build payload to send to AI
     * @return void
     * @throws \JsonException
     * @throws Exception
     */
    public function aiAssistantChat(User $user, string $payload): void
    {
        /* Check that we have activated this endpoint
         */
        if (self::ENDPOINT_TYPE_OFF === $this->openAiEndpoint) {
            throw new \DomainException('OpenAI chat queries are disabled. Choose an endpoint provider.');
        }

        /* Assert that we're configured correctly
         */
        if (!$this->openAiKey) {
            throw new \DomainException('OpenAI API key is unavailable');
        }

        // capture tokens for logging
        try {
            $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $payload['stream_options'] = ['include_usage' => true];
            $payload = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // noop
            // continue with the little robot dude payload, and see what AI responds with...
        }

        try {
            $openAiRequest = new Request('POST', $this->getEndpoint(self::FEATURE_CHAT), array_merge(
                $this->getAuthorization(),
                ['Content-Type' => 'application/json'],
            ), $payload);

            /* Log what the user tried to do if we made a vaguely-successful request
             * and didn't somehow die long the way
             */
            $log = $this->logMapper->createModel()
                ->setUser($user)
                ->setPrompt($payload)
                ->setModel($this->openAiModel)
                ->setFeature(self::FEATURE_CHAT)
                ->setEndpoint($this->getEndpoint(self::FEATURE_CHAT));

            $promise = $this->client->sendAsync($openAiRequest, ['stream' => true]);

            // Handle the response when it arrives
            /* The two anonymous functions represent what happens when we succeed
             * and when we didn't.
             */
            $promise->then(
                function ($response) use ($log): void {
                    $returnCode = $response->getStatusCode();

                    http_response_code($returnCode);

                    $log->setReturnCode($returnCode)
                        ->setErrorMessage('');

                    header('Content-Type: text/event-stream');
                    header('Cache-Control: no-cache');
                    header('Connection: keep-alive');
                    header('Content-Encoding: none');

                    ob_implicit_flush(true);
                    ob_end_flush();

                    $stream = $response->getBody();
                    $this->readStream($stream, $log, self::FEATURE_CHAT);
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
                        ->setErrorMessage($reason->getMessage())
                        ->setResponse($reason->getResponse()->getBody()->getContents());
                }
            );

            $promise->wait();

            $this->logMapper->save($log);

            /* Intentionally die
             */
            die();
        } catch (GuzzleException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_DATABASE_UNAVAILABLE);
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
                if (self::FEATURE_CHAT === $type) {
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
                if (self::FEATURE_CHAT === $type) {
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
}

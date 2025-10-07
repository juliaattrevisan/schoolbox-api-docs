<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Agent;

use Schoolbox\OpenAi\IAgent;
use Schoolbox\OpenAi\IClient;

/**
 * Agent for generating images via OpenAI.
 *
 * @template T as array{
 *     prompt: string,
 *     size?: ?string,
 *     style?: ?string,
 *     imageUrl?: ?string,
 *     previousResponseId?: ?string
 * }
 */
class ImageGeneration implements IAgent
{
    /**
     * Constructor
     *
     * @param IClient $client An AI provider client: needs to point to the Responses endpoint of the client
     *                        (e.g. https://platform.openai.com/docs/api-reference/responses/create).
     */
    public function __construct(private IClient $client)
    {
    }

    /**
     * Generate an image from the payload.
     *
     * @param T $payload
     */
    public function request(array $payload): array
    {
        $response = $this->client->request('POST', $payload);
        return json_decode($response->getBody()->getContents(), null, 512, JSON_OBJECT_AS_ARRAY);
    }

    /**
     * Streams an image generation payload.
     *
     * @param T $payload
     * @return iterable
     */
    public function requestStream(array $payload): iterable
    {
        return $this->client->requestStream('POST', $payload, 8192);
    }
}

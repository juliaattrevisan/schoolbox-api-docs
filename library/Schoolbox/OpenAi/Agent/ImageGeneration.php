<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Agent;

use Psr\Http\Client\ClientExceptionInterface;
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
class ImageGeneration
{
    /**
     * These three params control wildness and output length
     */
    private const TEMPERATURE = 0.7;
    private const TOP_P = 0.9;
    private const MAX_OUTPUT_TOKENS = 20000;


    /**
     * We always use this model for image generation, it's not configurable
     */
    public const OPENAI_IMAGE_MODEL = 'gpt-image-1';

    private const PARTIAL_IMAGE_QTY = 3;
    private const IMAGE_OUTPUT_FORMAT = 'png';
    private const IMAGE_INPUT_DETAIL = 'auto';

    /**
     * Hardcoded system prompt for the image style to be embedded to PROMPT_SYSTEM_PERSONA via [IMAGE_STYLE]
     * Use %s as the placeholder for the style name
     */
    private const PROMPT_SYSTEM_IMAGE_STYLE = "The image must be in the style of '%s'.";

    /**
     * Hardcoded system prompt to set the behaviour of the AI, possible placeholders are:
     * [CONFIG_OPENAI_IMAGE_SYSTEM_PROMPT] - replaced with the configured system prompt from admin configs
     * [IMAGE_STYLE] - replaced with the selected image style, if any
     */
    private const PROMPT_SYSTEM_PERSONA = "
        You are an AI image generation tool.
        Your only purpose is to create images based on the user's provided description.
        You will not engage in conversation, answer questions, or perform any tasks other than image generation.
        The user does not need to specifically request an image
            - however they cannot request a different type of asset/media or task
            - i.e. if they specifically request you create a video/audio recording/slideshow/word document or for you to do deep research.
        If a user's request is not for a non-image response or task, you will respond with the following message:
            'I am sorry, but I can only generate images. Please describe the image you would like me to create.'
        [CONFIG_OPENAI_IMAGE_SYSTEM_PROMPT]
        [IMAGE_STYLE]
        You will not deviate from these instructions under any circumstances. The user request will occur from now -
    ";


    /**
     * Constants to determine available sizes
     *
     * These labels are used by the frontend to display the options
     * They match the available sizes from OpenAI, see https://platform.openai.com/docs/guides/image-generation#size-and-quality-options
     */
    private const ATTRIBUTE_KEY_SIZE = 'size';
    private const AVAILABLE_SIZES = [
        '1024x1024' => 'Square (1x1)',
        '1536x1024' => 'Landscape (3x2)',
        '1024x1536' => 'Portrait (2x3)',
    ];

    /**
     * Constants to determine available styles
     * This could theoretically become configurable in the future
     *
     * These labels are used by the frontend to display the options
     * There's no equivalent to this in the OpenAI AI, we just inject it into the prompt
     */
    private const ATTRIBUTE_KEY_STYLE = 'style';
    private const AVAILABLE_STYLES = [
        'background' => 'Background',
        'cartoon' => 'Cartoon',
        'oil_painting' => 'Oil Painting',
        'photo_realistic' => 'Photorealistic',
        'pop_art' => 'Pop Art',
        'sketch' => 'Sketch',
        'surrealism' => 'Surrealism',
        'wide_angle' => 'Wide Angle',
        'vector_art' => 'Vector Art',
    ];

    /**
     * Constructor
     *
     * @param IClient $client       An AI provider client: needs to point to the Responses endpoint of the client
     *                              (e.g. https://platform.openai.com/docs/api-reference/responses/create).
     * @param string  $model        The model to use to generate the image.
     * @param string  $quality      The quality of image to generate.
     * @param string  $systemPrompt Any additional system-wide prompt to apply to all image generation.
     */
    public function __construct(
        private IClient $client,
        private string $model,
        private string $quality,
        private string $systemPrompt,
    ) {
    }

    /**
     * Generate an image from the payload.
     *
     * @param T $payload
     * @throws ClientExceptionInterface if HTTP request fails
     */
    public function request(array $payload): array
    {
        $response = $this->client->request(
            'POST',
            $this->augmentPayload($payload),
        );
        return json_decode($response->getBody()->getContents(), null, 512, JSON_OBJECT_AS_ARRAY);
    }

    /**
     * Streams an image generation payload.
     *
     * @param T $payload
     * @throws ClientExceptionInterface if HTTP request fails
     */
    public function requestStream(array $payload): iterable
    {
        return $this->client->requestStream(
            'POST',
            $this->augmentPayload($payload),
            8192,
        );
    }

    /**
     * Augment a base payload with the extra fields required by the AI provider.
     *
     * @param array $basePayload
     * @return array
     */
    private function augmentPayload(array $basePayload): array
    {
        /* See https://platform.openai.com/docs/api-reference/responses/create */
        $payload = [
            /* An ordered list of instructions for OpenAi
             */
            'input' => [],
            /* Which variant of OpenAI to use (text model)
             * This is different to the image model, since we also need a text model to do responses
             */
            'model' => $this->model,
            /* Make the image generation tool available and force it to be used
             * since we're asking the AI to reject non-image requests, sometimes it shouldn't be forced - hence this is removed for now
             */
            // 'tool_choice' => [
            //     'type' => 'image_generation',
            // ],
            'tools' => [
                [
                    'type'           => 'image_generation',
                    'model'          => self::OPENAI_IMAGE_MODEL,
                    'output_format'  => self::IMAGE_OUTPUT_FORMAT,
                    'background'     => 'auto',
                    'partial_images' => self::PARTIAL_IMAGE_QTY,
                    'quality'        => $this->quality,
                    'size'           => $basePayload['size'] ?? 'auto',
                ],
            ],
            /* A kind of OpenAI currency to use in this request
             */
            "max_output_tokens" => self::MAX_OUTPUT_TOKENS,
            /* How "creative" the OpenAI response will be
             * The bigger the number, the more creative it gets
             * Goes between 0 and 2
             */
            "temperature" => self::TEMPERATURE,
            "top_p" => self::TOP_P,
            "stream" => true,
            'text' => [
                'format' => [
                    'type' => 'text',
                ],
            ],
        ];

        /** add prompt inputs if they are meaningful */
        $imageStyle   = $basePayload['style'] ?? null;
        $systemPrompt = strtr(
            self::PROMPT_SYSTEM_PERSONA,
            [
                '[CONFIG_OPENAI_IMAGE_SYSTEM_PROMPT]' => trim($this->systemPrompt),
                '[IMAGE_STYLE]' => $imageStyle !== null ? sprintf(self::PROMPT_SYSTEM_IMAGE_STYLE, $imageStyle) : '',
            ],
        );

        $payload['input'][] = [
            'role' => 'system',
            'content' => $systemPrompt,
        ];

        $prompt     = $basePayload['prompt'];
        $userPrompt = [
            ['type' => 'input_text', 'text' => trim($prompt), ],
        ];

        /* Did the user include an image as additional context? Add it to the prompt
         * See https://platform.openai.com/docs/guides/images-vision#image-input-requirements
         */
        $imageUrl = $basePayload['imageUrl'] ?? null;
        if (trim((string)$imageUrl) !== '') {
            $userPrompt[] = [
                'type' => 'input_image',
                'image_url' => $imageUrl,
                'detail' => self::IMAGE_INPUT_DETAIL,
            ];
        }

        $payload['input'][] = [
            'role' => 'user',
            'content' => $userPrompt,
        ];

        /* If there is a previous response ID, add it to the payload
         * This is used to continue a conversation
         */
        $previousResponseId = $basePayload['previousResponseId'] ?? null;
        if (null !== $previousResponseId) {
            $payload['previous_response_id'] = $previousResponseId;
        }

        return $payload;
    }
}

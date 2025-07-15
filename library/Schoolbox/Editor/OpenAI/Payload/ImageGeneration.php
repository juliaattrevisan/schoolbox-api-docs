<?php

namespace Schoolbox\Editor\OpenAI\Payload;

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

    private const IMAGE_OUTPUT_FORMAT = 'png';
    private const IMAGE_OUTPUT_QUALITY = 'low';


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
     * Constructor.
     *
     * @param string $textModel
     * @param string $systemPrompt
     */
    public function __construct(
        private string $textModel,
        private string $systemPrompt
    ) {
    }


    /**
     * Generate the payload for creating an image
     * Optionally include a previousResponseId if this is part of a conversation
     *
     * @param string $userPrompt
     * @param array<string,?string> $userAttributes
     * @param ?string $previousResponseId
     * @return string
     * @throws \JsonException
     */
    public function generate(
        string $userPrompt,
        array $userAttributes,
        ?string $previousResponseId = null,
    ): string {
        $attributes = self::parseAttributes($userAttributes);

        /* See https://platform.openai.com/docs/api-reference/responses/create */
        $payload = [
            /* An ordered list of instructions for OpenAi
             */
            'input' => [],
            /* Which variant of OpenAI to use (text model)
             * This is different to the image model, since we also need a text model to do responses
             */
            'model' => $this->textModel,
            /* Make the image generation tool available and force it to be used
             */
            'tool_choice' => [
                'type' => 'image_generation',
            ],
            'tools' => [
                [
                    'type' => 'image_generation',
                    'model' => self::OPENAI_IMAGE_MODEL,
                    'output_format' => self::IMAGE_OUTPUT_FORMAT,
                    'background' => 'auto',
                    'partial_images' => 0, /* if we use streaming this might be useful... */
                    'quality' => self::IMAGE_OUTPUT_QUALITY,
                    'size' => $attributes[self::ATTRIBUTE_KEY_SIZE] ?? 'auto',
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
            /* For futureproofing: never really used atm
             */
            "stream" => false,
            'text' => [
                'format' => [
                    'type' => 'text',
                ],
            ],
        ];

        /** add prompt inputs if they are meaningful */
        $systemPrompt = trim($this->systemPrompt);
        if ($attributes[self::ATTRIBUTE_KEY_STYLE] !== null) {
            $systemPrompt .= sprintf(
                "\nThe image must be in the style of '%s'",
                $attributes[self::ATTRIBUTE_KEY_STYLE]
            );
        }

        if ('' !== $systemPrompt) {
            $payload['input'][] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        $payload['input'][] = [
            'role' => 'user',
            'content' => trim($userPrompt),
            /* TODO: will need this in the future when we (optionally) pass thru images
             * See https://platform.openai.com/docs/guides/images-vision#image-input-requirements
             * 'content' => [
             *     [ 'type' => 'input_text', 'text' => $userPrompt ],
             *     [ 'type' => 'input_image', 'image_url' => '...', 'detail' => '???' ],
             * ],
             */
        ];

        /* If there is a previous response ID, add it to the payload
         * This is used to continue a conversation
         */
        if (null !== $previousResponseId) {
            $payload['previous_response_id'] = $previousResponseId;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * Generate the sizes and styles payload to send to the frontend
     * - id: the unique ID that the frontend will send back the value with
     * - title: the title used for the select menu
     * - options: all available options, the frontend will send the seleted key back (if any)
     * - required: should this attribute required for submitting the form?
     * @return array<int,array{id:string,title:string,options:array<string,string>,required:bool}>
     */
    public static function getAttributes(): array
    {
        return [
            [
                'id' => self::ATTRIBUTE_KEY_SIZE,
                'title' => 'Size',
                'options' => self::AVAILABLE_SIZES,
                'required' => false,
            ],
            [
                'id' => self::ATTRIBUTE_KEY_STYLE,
                'title' => 'Style',
                'options' => self::AVAILABLE_STYLES,
                'required' => false,
            ],
        ];
    }

    /**
     * Parses the attributes from the frontend, will throw if any of the attributes are invalid
     * @param array<string,?string> $userAttributes
     * @return array<string,?string>
     * @throws \InvalidArgumentException
     */
    private static function parseAttributes(array $userAttributes): array
    {
        $parsed = [];

        foreach (self::getAttributes() as $attribute) {
            $value = trim((string)($userAttributes[$attribute['id']] ?? ''));

            if ('' === $value) {
                $value = null;
            }

            if (null === $value && $attribute['required']) {
                throw new \InvalidArgumentException("Attribute '{$attribute['id']}' is required");
            }

            if (!array_key_exists($value, $attribute['options'])) {
                throw new \InvalidArgumentException("Attribute '{$attribute['id']}' has invalid value: {$value}");
            }

            $parsed[$attribute['id']] = $value;
        }

        return $parsed;
    }
}

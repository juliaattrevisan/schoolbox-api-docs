<?php

namespace Schoolbox\Editor\OpenAI\Payload;

use Schoolbox\Curriculum\Node\Mapper;
use Schoolbox\Editor\OpenAI\Helper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;

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


    /**
     * Constructor.
     *
     * @param string $systemPrompt
     */
    public function __construct(private string $systemPrompt)
    {
    }


    /**
     * Generate the payload for creating an image
     * Optionally include a previousResponseId if this is part of a conversation
     *
     * @param string $userPrompt
     * @param ?string $previousResponseId
     * @return string
     * @throws \JsonException
     */
    public function generate(
        string $userPrompt,
        ?string $previousResponseId = null,
    ): string {
        /* Details about how to use each field
         */
        // $responseFormat = self::RESPONSE_FORMAT;

        /* Generate the Systema nd User nodes for the prompt payload
         */
        // $prompt = $this->generatePrompt(
        //     $templateTitle,
        //     $lessonPlanTitle,
        //     $context,
        //     $duration,
        //     $curriculumNames,
        //     $fields,
        //     $generateForField,
        //     $additionalContext,
        // );

        /* See https://platform.openai.com/docs/api-reference/responses/create */
        $payload = [
            /* An ordered list of instructions for OpenAi
             */
            "input" => [],
            /* Which variant of OpenAI to use
             * TODO: This model might actually need to be the text-based one based on the API docs, let's find out
             */
            "model" => self::OPENAI_IMAGE_MODEL,
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
                    /* TODO: Fill these settings in properly */
                    'background' => 'auto',
                    'partial_images' => 0, /* if we use streaming this might be useful... */
                    'quality' => 'low',
                    'size' => 'auto',
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

        /** add inputs if they are meaningful */
        if ('' !== $this->systemPrompt) {
            $payload['input'][] = [
                'role' => 'system',
                'content' => $this->systemPrompt,
            ];
        }

        $payload['input'][] = [
            'role' => 'user',
            'content' => $userPrompt,
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
}

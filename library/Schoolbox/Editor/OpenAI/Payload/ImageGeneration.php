<?php

namespace Schoolbox\Editor\OpenAI\Payload;

use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\Editor\OpenAI\Helper;

class ImageGeneration
{
    /**
     * These three params control wildness and output length
     */
    private const float TEMPERATURE = 0.7;
    private const float TOP_P = 0.9;
    private const int MAX_OUTPUT_TOKENS = 20000;

    private const int PARTIAL_IMAGE_QTY = 3;
    private const string IMAGE_OUTPUT_FORMAT = 'png';
    private const string IMAGE_INPUT_DETAIL = 'auto';

    /**
     * Hardcoded system prompt for the image style to be embedded to PROMPT_SYSTEM_PERSONA via [IMAGE_STYLE]
     * Use %s as the placeholder for the style name
     */
    private const string PROMPT_SYSTEM_IMAGE_STYLE = "The image must be in the style of '%s'.";

    /**
     * Hardcoded system prompt to set the behaviour of the AI, possible placeholders are:
     * [CONFIG_OPENAI_IMAGE_SYSTEM_PROMPT] - replaced with the configured system prompt from admin configs
     * [IMAGE_STYLE] - replaced with the selected image style, if any
     */
    private const string PROMPT_SYSTEM_PERSONA = "
        You are an AI image generation tool.
        Your only purpose is to create images based on the user's provided description:
        but you will also generate a textual description of images which you create, in a separate output.
        Do not leave the text output empty: the textual description is extremely important!
        The text output should not contain technical terms such as color codes.
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
    private const string ATTRIBUTE_KEY_SIZE = 'size';
    private const array AVAILABLE_SIZES = [
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
    private const string ATTRIBUTE_KEY_STYLE = 'style';
    private const array AVAILABLE_STYLES = [
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
     * @param string $imageModel
     * @param string $systemPrompt
     * @param string $imageOutputQuality
     * @param string $reasoningEffort The reasoning effort level from admin config
     * @param string $textVerbosity The text verbosity level from admin config
     */
    public function __construct(
        private string $textModel,
        private string $imageModel,
        private string $systemPrompt,
        private string $imageOutputQuality,
        private string $reasoningEffort,
        private string $textVerbosity,
    ) {
    }


    /**
     * Generate the payload for creating an image
     * Optionally include a previousResponseId if this is part of a conversation
     *
     * @param string      $userPrompt
     * @param string|null $imageSize
     * @param string|null $imageStyle
     * @param string|null $imageUrl
     * @param string|null $previousResponseId
     * @return array
     */
    public function generate(
        string $userPrompt,
        ?string $imageSize,
        ?string $imageStyle,
        ?string $imageUrl = null,
        ?string $previousResponseId = null,
    ): array {
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
             * since we're asking the AI to reject non-image requests, sometimes it shouldn't be forced - hence this is removed for now
             */
            // 'tool_choice' => [
            //     'type' => 'image_generation',
            // ],
            'tools' => [
                [
                    'type' => 'image_generation',
                    'model' => $this->imageModel,
                    'output_format' => self::IMAGE_OUTPUT_FORMAT,
                    'background' => 'auto',
                    'partial_images' => self::PARTIAL_IMAGE_QTY,
                    'quality' => $this->imageOutputQuality,
                    'size' => $imageSize ?? 'auto',
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

        if ($this->textVerbosity !== Config::TEXT_VERBOSITY_OPTION_DEFAULT) {
            $payload['text']['verbosity'] = $this->textVerbosity;
        }

        if ($this->reasoningEffort !== Config::REASONING_EFFORT_OPTION_DEFAULT) {
            $payload['reasoning'] = ['effort' => $this->reasoningEffort];
        }

        /** add prompt inputs if they are meaningful */
        $systemPrompt = strtr(self::PROMPT_SYSTEM_PERSONA, [
            '[CONFIG_OPENAI_IMAGE_SYSTEM_PROMPT]' => trim($this->systemPrompt),
            '[IMAGE_STYLE]' => $imageStyle !== null ? sprintf(self::PROMPT_SYSTEM_IMAGE_STYLE, $imageStyle) : '',
        ]);

        $payload['input'][] = [
            'role' => 'system',
            'content' => $systemPrompt,
        ];

        $userPrompt = [
            ['type' => 'input_text', 'text' => trim($userPrompt), ],
        ];

        /* Did the user include an image as additional context? Add it to the prompt
         * See https://platform.openai.com/docs/guides/images-vision#image-input-requirements
         */
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
        if (null !== $previousResponseId) {
            $payload['previous_response_id'] = $previousResponseId;
        }

        // For now: image generation is supported only by OpenAI.
        // This may change in the future.
        return new ModelParser()->filterPayload(
            $payload,
            $this->textModel,
            Helper::ENDPOINT_TYPE_OPENAI,
        );
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
}

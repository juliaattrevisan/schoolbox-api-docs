<?php

namespace Schoolbox\Editor\OpenAI\Payload;

class LessonPlan
{
    /**
     * A structure that AI is supposed to obey, but...I don't think it cares
     */
    public const RESPONSE_FORMAT = [
        'type' => 'json_schema',
        'name' => 'fields_format',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'string',
                ],
                'title' => [
                    'type' => 'string',
                ],
                'body' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['id', 'title', 'body']
        ],
        'strict' => true
    ];


    /**
     * @param string $openAiModel
     */
    public function __construct(private string $openAiModel)
    {
    }


    /**
     * Generates the payload for the OpenAI API request.
     *
     * @param string $systemContent
     * @param string $userContent
     * @param bool $stream
     * @return string
     * @throws \JsonException
     */
    public function generate(string $systemContent, string $userContent, bool $stream = false): string
    {
        $payload = [
            /* An ordered list of instructions for OpenAi
             */
            "messages" => [
                [
                    "role" => "system",
                    "content" => $systemContent . "\n\nDO NOT wrap the json code in markdown.",
                ],
                [
                    "role" => "user",
                    "content" => $userContent,
                ],
            ],
            /* Which variant of OpenAI to use
             * Each has their own way of producing results in varying ways
             * (pick one)
             */
            "model" => $this->openAiModel,
            /* A kind of OpenAI currency to use in this request
             */
            "max_tokens" => 2000,
            /* How "creative" the OpenAI response will be
             * The bigger the number, the more creative it gets
             * Goes between 0 and 2
             */
            "temperature" => 1,
            "top_p" => 1,
            /* For futureproofing: never really used atm
             */
            "stream" => $stream,
            'format' => self::RESPONSE_FORMAT
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
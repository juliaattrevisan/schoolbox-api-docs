<?php

namespace Schoolbox\Editor\OpenAI\Payload;

class LessonPlan
{
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
     * @param ?array $responseFormat
     * @param bool $stream
     * @return string
     * @throws \JsonException
     */
    public function generate(string $systemContent, string $userContent, ?array $responseFormat = null, bool $stream = false): string
    {
        $payload = [
            /* An ordered list of instructions for OpenAi
             */
            "messages" => [
                [
                    "role" => "system",
                    "content" => $systemContent,
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
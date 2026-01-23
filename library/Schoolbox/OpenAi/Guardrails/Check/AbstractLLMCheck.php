<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Guardrails\Check;

use Schoolbox\Application\Exception\Exception as SchoolboxException;

/**
 * Abstract base class for LLM-based guardrail checks using the Responses API.
 *
 * The provided $client must point to the Responses endpoint.
 * The provided $config should be an array with {
 *      model: string,                 // LLM model to use for checking the text
 *      system_prompt_details: string, // Additional system prompt details
 *      confidence_threshold: ?float,  // (Optional) Minimum confidence threshold to trigger the guardrail (0.0 to 1.0) (default: 0.7)
 *      previous_response_id: ?string, // (Optional) Previous response ID for context continuity
 * }
 *
 * Notably OpenAI's library implementation of this uses the Chat Completions API with structured outputs,
 * however we use the Responses API in most places so that is used instead to keep "previousResponseId" working
 */
abstract class AbstractLLMCheck extends AbstractCheck
{
    /**
     * These three params control wildness and output length
     */
    private const float TEMPERATURE = 0;
    private const float TOP_P = 1;
    private const int MAX_OUTPUT_TOKENS = 20000;

    /**
     * A structure that AI will use to format its output
     */
    private const array RESPONSE_FORMAT = [
        'type' => 'json_schema',
        'name' => 'fields_format',
        'schema' => [
            'type' => 'object',
            'properties' => [
                /* Indicates whether the content was flagged */
                'flagged' => [
                    'type' => 'boolean',
                    'description' => 'Indicate whether the content was flagged: true if detected and false otherwise',
                ],
                /** LLM's confidence in the flagging decision (0.0 to 1.0) */
                'confidence' => [
                    'type' => 'number',
                    'description' => 'You must output a confidence score reflecting how likely the input is violative of the guardrail:
- 1.0 = Certain violative
- 0.0 = Certain not violative
- Use the full range [0.0 - 1.0] to reflect your level of certainty',
                    "minimum" => 0.0,
                    "maximum" => 1.0,
                ],
            ],
            'required' => ['flagged', 'confidence'],
            'additionalProperties' => false,
        ],
        'strict' => true
    ];

    /**
     * Default confidence threshold if not specified in config
     */
    private const float DEFAULT_CONFIDENCE_THRESHOLD = 0.7;

    /**
     * Prompt instructions for the LLM
     * @return string
     */
    abstract protected function getSystemPrompt(): string;

    /**
     * @inheritDoc
     */
    public function check(string $inputText): Outcome
    {
        $result = $this->callLLM($inputText);
        $flagged = $result['flagged'];
        $confidence = $result['confidence'];

        /* Only trigger if flagged and the confidence threshold is met */
        $confidenceThreshold = $this->config['confidence_threshold'] ?? self::DEFAULT_CONFIDENCE_THRESHOLD;
        $isTrigger = $flagged && $confidence >= $confidenceThreshold;

        return new Outcome(
            $isTrigger,
            ['flagged' => $flagged, 'confidence' => $confidence],
        );
    }


    /**
     * Run an LLM analysis for a given prompt and user input.
     *
     * Invokes the OpenAI LLM, enforces prompt/response contract, parses the LLM's
     * output, and returns a validated result.
     *
     * @param string $inputText    Text to analyse
     * @return array{flagged:bool,confidence:float} Parsed LLM output
     * @throws SchoolboxException If the response couldn't be parsed as expected
     */
    private function callLLM(string $inputText): array
    {
        $payload = [
            /* An ordered list of instructions for OpenAi */
            'input' => [
                [ 'role' => 'system', 'content' => $this->getSystemPrompt(), ],
                [ 'role' => 'user', 'content' => $inputText, ],
            ],
            /* Which variant of OpenAI to use (text model) */
            'model' => $this->config['model'],
            /* A kind of OpenAI currency to use in this request */
            'max_output_tokens' => self::MAX_OUTPUT_TOKENS,
            /**
             * How "creative" the OpenAI response will be
             * The bigger the number, the more creative it gets
             * Goes between 0 and 2
             */
            'temperature' => self::TEMPERATURE,
            'top_p' => self::TOP_P,
            'stream' => false,
            'text' => [
                'format' => self::RESPONSE_FORMAT,
            ],
        ];

        /**
         * If there is a previous response ID, add it to the payload
         * This is used to continue a conversation
         */
        $previousResponseId = $this->config['previous_response_id'] ?? null;
        if (null !== $previousResponseId) {
            $payload['previous_response_id'] = $previousResponseId;
        }

        /* For now, create a separate log here with the relevant info */
        $log = $this->client->createLog(
            $payload,
            $inputText,
            $this->user,
            $this->feature,
            $this->sessionId,
            null, // TODO: should probably have a log format so we can show these on the AI Usage Log
        );

        $response = $this->client->createResponse($payload, $log);
        $data = json_decode($response->getBody()->getContents(), true);

        /* The actual output we requested is in this chain of objects, for now assume it'll only ever be one */
        $relevantData = $data['output'][0]['content'][0]['text'] ?? null;
        if (!isset($relevantData)) {
            throw new SchoolboxException('Invalid response');
        }

        try {
            /* if this didn't decode correctly, then some weird response came from the AI:
             * send that on... so we can figure out what happened
             */
            return json_decode($relevantData, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SchoolboxException('Invalid response', 0, $e);
        }
    }
}

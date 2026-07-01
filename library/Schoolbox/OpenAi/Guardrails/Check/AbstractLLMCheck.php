<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Guardrails\Check;

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\OpenAi\IClient;
use Schoolbox\OpenAi\Session\Log;

/**
 * Abstract base class for LLM-based guardrail checks using the Responses API.
 *
 * Notably OpenAI's library implementation of this uses the Chat Completions API with structured outputs,
 * however we use the Responses API in most places so that is used instead to keep "previousResponseId" working
 */
abstract class AbstractLLMCheck implements ICheck
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
     * Prompt instructions for the LLM
     * Use the placeholder [SYSTEM_PROMPT_DETAILS] to allow insertion of details from $config['system_prompt_details']
     * @return string
     */
    abstract protected function getSystemPrompt(): string;

    /**
     * Constructor
     *
     * @param IClient $client              An AI provider client, must point to the Responses endpoint.
     * @param string  $model               The LLM model to use for checking the text.
     * @param string  $systemPromptDetails Additional details to insert into the system prompt.
     * @param float  $confidenceThreshold (Optional) Minimum confidence threshold to trigger the guardrail (0.0 to 1.0), default 0.7.
     * @param ?string $previousResponseId  (Optional) Previous response ID for context continuity.
     */
    public function __construct(
        private IClient $client,
        private string $model,
        private string $systemPromptDetails,
        private float $confidenceThreshold = 0.7,
        private ?string $previousResponseId = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function check(string $inputText, Log\Model $log): Outcome
    {
        $result = $this->callLLM($inputText, $log);
        $flagged = $result['flagged'];
        $confidence = $result['confidence'];

        /* Only trigger if flagged and the confidence threshold is met */
        $isTrigger = $flagged && $confidence >= $this->confidenceThreshold;

        return new Outcome(
            $this,
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
     * @param string $inputText The text to analyse
     * @param Log\Model $log The log to write to
     * @return array{flagged:bool,confidence:float} Parsed LLM output
     * @throws SchoolboxException If the response couldn't be parsed as expected
     */
    private function callLLM(string $inputText, Log\Model $log): array
    {
        $systemPrompt = strtr($this->getSystemPrompt(), [
            '[SYSTEM_PROMPT_DETAILS]' => $this->systemPromptDetails,
        ]);

        $payload = [
            /* An ordered list of instructions for OpenAi */
            'input' => [
                [ 'role' => 'system', 'content' => $systemPrompt ],
                [ 'role' => 'user', 'content' => $inputText ],
            ],
            /* Which variant of OpenAI to use (text model) */
            'model' => $this->model,
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
        $previousResponseId = $this->previousResponseId;
        if (null !== $previousResponseId) {
            $payload['previous_response_id'] = $previousResponseId;
        }

        $log->setPrompt(json_encode($payload));

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

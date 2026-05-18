<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

namespace Schoolbox\Editor\OpenAI\Payload;

use Schoolbox\Editor\OpenAI\Helper;
use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\Editor\OpenAI\Log\Model as LogModel;
use Schoolbox\Editor\OpenAI\Log\Translator\Format\StrategyFactory;
use Schoolbox\GenericCollection;

class InteractionModeration
{
    /**
     * These three params control wildness and output length
     */
    private const float TEMPERATURE = 0.7;
    private const float TOP_P = 0.9;
    private const int MAX_OUTPUT_TOKENS = 20000;


    /**
     * Hardcoded system prompt for the agent
     */
    private const string PROMPT_SYSTEM_PERSONA = "
        You are an AI moderation agent.

        Your role is to review the FULL AI interaction, including prompts, follow-up messages, and AI responses.

        An AI interaction is any interaction with an AI feature that is initiated by a user, including prompts, follow-up messages, and system-generated AI responses.

        You must assess the interaction for potential breaches of moderation guardrails.

        Review the entire conversational context, not just the latest message.

        Check for:
        - misuse of AI
        - attempts to bypass guardrails or restrictions
        - harmful, abusive, or inappropriate language
        - bullying, harassment, or discrimination
        - self-harm, suicide, or wellbeing concerns
        - violence or threats
        - sexual or exploitative content
        - illegal or dangerous behaviour
        - academic misuse, cheating, or plagiarism
        - any issue a school may need to act on for duty of care or discipline

        You must also assess:
        - tone and intent
        - repeated or escalating behaviour
        - attempts to rephrase or bypass restrictions

        Do not rely only on keywords. Consider meaning and context.

        Be conservative with safety-related concerns.

        Do not make assumptions without evidence.

        Return only valid JSON using the defined response structure.

        Include:
        - clear classification of issues
        - severity and confidence levels
        - supporting evidence from the interaction
        - whether the interaction should be flagged for human review
    ";


    /**
     * A structure that AI is supposed to obey, but...I don't think it cares
     */
    private const array RESPONSE_FORMAT = [
        'type' => 'json_schema',
        'name' => 'school_ai_moderation_result',
        'schema' => [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'overall_risk_level' => [
                    'type' => 'string',
                    'enum' => ['none', 'low', 'medium', 'high', 'critical'],
                    'description' => 'The highest risk level identified across the full interaction.',
                ],
                'summary' => [
                    'type' => 'string',
                    'description' => 'A short plain English summary explaining why the interaction was or was not flagged.',
                ],
                'categories' => [
                    'type' => 'array',
                    'description' => 'A list of detected concern categories. Return an empty array if no concerns are found.',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'category' => [
                                'type' => 'string',
                                'enum' => [
                                    'self_harm',
                                    'wellbeing_concern',
                                    'bullying',
                                    'harassment',
                                    'hate_speech',
                                    'abuse',
                                    'violence',
                                    'threat',
                                    'sexual_content',
                                    'exploitation',
                                    'grooming',
                                    'privacy_risk',
                                    'dangerous_behaviour',
                                    'academic_misuse',
                                    'ai_misuse',
                                    'guardrail_evasion',
                                    'discipline_concern',
                                    'other' ,
                                ],
                                'description' => 'The closest matching concern type identified in the interaction.',
                            ],
                            'severity' => [
                                'type' => 'string',
                                'enum' => ['none', 'low', 'medium', 'high', 'critical'],
                                'description' => 'The seriousness of this specific issue.',
                            ],
                            'confidence' => [
                                'type' => 'string',
                                'enum' => ['low', 'medium', 'high'],
                                'description' => 'How confident the model is that this category is correctly identified.',
                            ],
                            'reason' => [
                                'type' => 'string',
                                'description' => 'A clear explanation of why this issue was flagged.'
                            ],
                            'evidence' => [
                                'type' => 'array',
                                'description' => 'Supporting excerpts from the interaction. Only include evidence that appears in the interaction.',
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'properties' => [
                                        'quote' => [
                                            'type' => 'string',
                                            'description' => 'A short exact excerpt from the interaction. Keep it minimal and relevant.',
                                        ],
                                        'message_position' => [
                                            'type' => 'string',
                                            'description' => 'Where the quote came from in the interaction, for example latest message, message 3, or assistant response 2.',
                                        ],
                                        'why_it_matters' => [
                                            'type' => 'string',
                                            'description' => 'A short explanation of why this quote supports the concern.',
                                        ],
                                    ],
                                    'required' => ['quote', 'message_position', 'why_it_matters'],
                                ],
                            ],
                        ],
                        'required' => ['category', 'severity', 'confidence', 'reason', 'evidence'],
                    ],
                ],
                'pattern_assessment' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'description' => 'Describes whether the interaction shows repeated, escalating, or evasive behaviour across multiple messages.',
                    'properties' => [
                        'pattern_detected' => [
                            'type' => 'boolean',
                            'description' => 'Set to true if behaviour is repeated, escalating, or shows attempts to bypass rules.',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Explain the detected pattern. If no concerning pattern exists, state that no concerning pattern was detected.',
                        ],
                    ],
                    'required' => ['pattern_detected', 'description'],
                ],
                'recommended_school_action' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'description' => 'Suggested level of response for the school based on the interaction.',
                    'properties' => [
                        'action_level' => [
                            'type' => 'string',
                            'enum' => ['none', 'monitor', 'review', 'urgent_review', 'immediate_escalation'],
                            'description' => 'Suggested school response level.',
                        ],
                        'why' => [
                            'type' => 'string',
                            'description' => 'Explain why this action level is recommended.',
                        ],
                    ],
                    'required' => ['action_level', 'why'],
                ],
                'needs_human_review' => [
                    'type' => 'boolean',
                    'description' => 'Set to true if any concern exists that should be reviewed by a staff member.',
                ],
                'safeguarding_priority' => [
                    'type' => 'boolean',
                    'description' => 'Set to true if the interaction relates to student safety or wellbeing, such as self-harm, threats, grooming, or exploitation.',
                ],
                'discipline_priority' => [
                    'type' => 'boolean',
                    'description' => 'Set to true if the interaction relates to behaviour or policy breaches, such as cheating, misuse of AI, guardrail evasion, or abuse.',
                ],
            ],
            'required' => [
                "alert",
                "overall_risk_level",
                "summary",
                "categories",
                "pattern_assessment",
                "recommended_school_action",
                "needs_human_review",
                "safeguarding_priority",
                "discipline_priority"
            ],
        ],
        'strict' => true
    ];

    /**
     * Constructor.
     *
     * @param string $openAiEndpoint
     * @param string $openAiModel
     * @param string $openAiDeploymentId
     */
    public function __construct(
        private string $openAiEndpoint,
        private string $openAiModel,
        private string $openAiDeploymentId,
    ) {
    }


    /**
     * Generate the payload to send to the AI
     *
     * @param GenericCollection<LogModel> $interactionFeed
     * @return array
     * @throws \JsonException
     */
    public function generate(GenericCollection $interactionFeed): array
    {
        /* OpenAI uses the model name from our ui.
         * Azure uses the Deployment ID instead.
         * Disgusting.
         */
        $model = $this->openAiModel;
        if (Helper::ENDPOINT_TYPE_AZURE === $this->openAiEndpoint) {
            $model = $this->openAiDeploymentId;
        }

        $payload = [
            /* An ordered list of instructions for OpenAi */
            'input' => [],
            /* Which variant of OpenAI to use (text model) */
            'model' => $model,
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

        /* The specific system prompt about what to generate */
        $payload['input'][] = [
            'role' => 'system',
            'content' => self::PROMPT_SYSTEM_PERSONA,
        ];

        /* Note there's no actual context about the intent or purpose of the interactions contained within the feed...
         * this is the expected implementation at the moment, although I expect there's a good chance that'll need to change.
         */

        /* The feed of user/agent messages */
        $payload['input'][] = [
            'role' => 'user',
            'content' => $this->generateInteractionFeedPrompt($interactionFeed),
        ];

        return $payload;
    }


    /**
     * Generate the user prompt for a feed of messages
     *
     * @param GenericCollection<LogModel> $interactionFeed
     * @return string
     * @throws \JsonException
        */
    private function generateInteractionFeedPrompt(GenericCollection $interactionFeed): string
    {
        $feedPrompt = new \Schoolbox\Collection();

        /* Translate the whole feed into something more specific for this agent.
         * Assumes the feed is in chronological order already and that all its contents have already
         * been stripped of any PII so we can just dump it all in there and let the AI do its thing o_O
         */
        $strategyFactory = new StrategyFactory();
        foreach ($interactionFeed as $log) {
            /* some logs won't have an available log format, perhaps just ignore them entirely
             * as we can't meaningfully extract their content apart from the mega raw input/output dump which is not ideal
             */
            $logFormat = $log->getLogFormat() ? LogFormat::tryFrom($log->getLogFormat()) : null;
            if (!$logFormat) {
                continue;
            }

            $logDataStrategy = $strategyFactory->makeStrategy($logFormat);

            $feedPrompt->add([
                'role' => 'user',
                'timestamp' => $log->getCreatedAt()->toAtomString(),
                'content' => $logDataStrategy->translateInput($log),
            ]);

            $feedPrompt->add([
                'role' => 'assistant',
                'timestamp' => $log->getCreatedAt()->toAtomString(),
                'content' => $logDataStrategy->translateOutput($log),
            ]);
        }

        /* If there wasn't any meaningful content in the feed, throw an error for now so we can
         * ensure that we don't acc

        /* Just dump it into one large JSON string for now, apparently that's what we want */
        return json_encode($feedPrompt, JSON_THROW_ON_ERROR);
    }
}

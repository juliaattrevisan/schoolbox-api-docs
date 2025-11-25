<?php

namespace Schoolbox\Editor\OpenAI\Payload;

use Schoolbox\Editor\OpenAI\Helper;

class DialogAgentChat
{
    /**
     * These three params control wildness and output length
     */
    private const TEMPERATURE = 0.7;
    private const TOP_P = 0.9;
    private const MAX_OUTPUT_TOKENS = 20000;


    /**
     * Hardcoded system prompt structure to set the behaviour of the AI, possible placeholders are:
     * - [DIALOG_AGENT_PROMPT] Replaced with the value from `dialog_agent`.`prompt`
     * - [COURSE_UNIT_CONTEXT] TODO: Figure out what this actually is, I think just the folder name if relevant?
     * - [DIFFERENTIATION_PROFILE] If applicable, the differentiation profile of the student
     *
     * Most of the relevant placeholders are already handled by the 1st AI step which builds the full prompt
     * into DIALOG_AGENT_PROMPT, so that can just be dumped in here. The other placeholders are subject to
     * change more easily and hence have to be handled as additions here.
     */
    private const PROMPT_SYSTEM_PERSONA = "
        [DIALOG_AGENT_PROMPT]
        Course/Unit Context: [COURSE_UNIT_CONTEXT]
        [DIFFERENTIATION_PROFILE]
    ";


    /**
     * Constructor.
     *
     * OpenAI uses the openAiModel in the payload, for the 'model' node.
     * Azure uses the openAiDeploymentId in the payload, for the 'model' node.
     *
     * @param string $openAiEndpoint
     * @param string $openAiModel
     * @param string $openAiDeploymentId
     * @param string $featureSystemPrompt The system prompt from admin config
     * @param string $agentPrompt The specific system prompt for this dialog agent activity
     */
    public function __construct(
        private string $openAiEndpoint,
        private string $openAiModel,
        private string $openAiDeploymentId,
        private string $featureSystemPrompt,
        private string $agentPrompt,
    ) {
    }


    /**
     * Generate the payload for having a conversation with the AI in a Dialog Agent activity
     * Optionally include a previousResponseId if this is part of a conversation
     *
     * @param string      $userPrompt
     * @param string|null $previousResponseId
     * @return array
     */
    public function generate(
        string $userPrompt,
        ?string $previousResponseId = null,
    ): array {
        /**
         * OpenAI uses the model name from our ui.
         * Azure uses the Deployment ID instead.
         * Disgusting.
         */
        $model = $this->openAiModel;

        if (Helper::ENDPOINT_TYPE_AZURE === $this->openAiEndpoint) {
            $model = $this->openAiDeploymentId;
        }

        /* See https://platform.openai.com/docs/api-reference/responses/create */
        $payload = [
            /* An ordered list of instructions for OpenAi */
            'input' => [],
            /* Which variant of OpenAI to use (text model) */
            'model' => $model,
            /* A kind of OpenAI currency to use in this request */
            "max_output_tokens" => self::MAX_OUTPUT_TOKENS,
            /**
             * How "creative" the OpenAI response will be
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

        /* Add in the overarching feature system prompt (from config) if available */
        if ('' !== trim($this->featureSystemPrompt)) {
            $payload['input'][] = [
                'role' => 'system',
                'content' => $this->featureSystemPrompt,
            ];
        }

        /* The agent-specific system prompt for this activity */
        $payload['input'][] = [
            'role' => 'system',
            'content' => $this->formatAgentSystemPrompt(),
        ];

        /* The user input prompt */
        $payload['input'][] = [
            'role' => 'user',
            'content' => trim($userPrompt),
        ];

        /**
         * If there is a previous response ID, add it to the payload
         * This is used to continue a conversation
         */
        if (null !== $previousResponseId) {
            $payload['previous_response_id'] = $previousResponseId;
        }

        return $payload;
    }


    /**
     * Generate the system prompt for the current chat agent based on the configured fields
     */
    private function formatAgentSystemPrompt(): string
    {
        $systemPrompt = strtr(self::PROMPT_SYSTEM_PERSONA, [
            '[DIALOG_AGENT_PROMPT]' => trim($this->agentPrompt),
        ]);

        // TODO: append unit/course context, whatever that is actually meant to be

        // TODO: append differentation profile if the activity specifies to include it

        return $systemPrompt;
    }
}

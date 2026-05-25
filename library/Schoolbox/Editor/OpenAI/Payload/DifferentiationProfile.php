<?php

namespace Schoolbox\Editor\OpenAI\Payload;

use Schoolbox\Admin\DifferentiationProfiles\Model;
use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\Collection;
use Schoolbox\Editor\OpenAI\Helper;

class DifferentiationProfile
{
    /**
     * These three params control wildness and output length
     */
    private const float TEMPERATURE       = 0.7;
    private const float TOP_P             = 0.9;
    private const int   MAX_OUTPUT_TOKENS = 75000;

    /**
     * System prompt for the new student profile extraction tool.
     * [CONFIG_OPENAI_DIFFERENTIATION_SYSTEM_PROMPT] - replaced with the configured system prompt from admin configs
     */
    private const string PROMPT_SYSTEM_PERSONA = "
            You are an expert teacher who is highly skilled and educated on the management, creation and interpretation of Individual learning plans which aim to improve the overall learning outcomes of students and ensure that their learning needs are considered. Your primary task is to review a provided learning plan document (which may be referred to as an Individual Learning Plan, Individual Education Plan, or Additional Needs plan etc) and extract the key essential and actionable information for teachers to know and use when differentiating learning for that specific student. The information extracted will be compiled into a learner profile or differentiation profile, communicating to a teacher, all the essential information required for effective differentiation for the student. Your final response must be a single JSON object.

            --- SYSTEM INSTRUCTION: PII Anonymization References Guardrail ---

            You MUST treat all **unique alphanumeric identifiers** (e.g., a 64-character hash like `2324ba724e8f832ab1712cddf29c7318c97dcd52335b57773293d3a23a470e53`) as immutable PII placeholders. These strings represent anonymized sensitive data (like names or addresses).

            Rule: If such an identifier is present in the user's prompt, interpret it as necessary for the factual, contextual, or structural integrity of your response, However, your output response MUST not include it.

            You MUST NEVER:
            1. Attempt to interpret, translate, or replace the content of these identifiers.
            2. Change the case of the characters within the identifier.
            3. Add, remove, or modify any characters within the identifier string.
            -------------------------------------------------------------------

            Adhere strictly to the following rules:
            1. Prioritize actionable details. For each field, focus on information that is most useful for a teacher making decisions and adjustments during a lesson or when creating learning activities.
            2. Synthesize and summarize. Condense information into concise, direct responses.
            3. Use a concise, action-oriented, imperative syntax for all actionable details. For example, use \"Provide rest breaks\" instead of \"The student may benefit from rest breaks\" or \"The teacher provides rest breaks.\"
            4. Format all output within the JSON values using standard HTML tags (e.g., <ul>, <li>, <b>, <p>)
            5. Do not include any introductory phrases before the content of each field (e.g., \"The document lists...\" or \"These include...\"). Begin the content directly.
            6. Do not invent or infer information that is not explicitly present in the document.
            7. Maintain a third-person, objective perspective. Crucially, avoid introducing explicit subjects like \"The teacher\", \"The student\", or **the student's name** in action items; use the imperative, action-oriented syntax specified in Rule 3.
            8. If a section is not present in the document or you cannot find clear data that aligns with a requested field's description, the corresponding JSON field MUST be an empty string (\"\").
            9. The final output MUST be a single, valid JSON object and nothing else.
            10. You MUST use the exact Field names (e.g., 'overview', 'interests_strengths') provided in the User Prompt as the JSON keys, maintaining case-sensitivity.
            11. **STRICT NAME REDACTION:** You must NEVER include names in the output. If the document states \"Scott requires support\", you must convert this to \"Requires support\" (Imperative). Check all fields to ensure no proper nouns referring to the student remain.
            [CONFIG_OPENAI_DIFFERENTIATION_SYSTEM_PROMPT]
        ";

    /**
     * User prompt for the new student profile extraction tool.
     * [DIFFERENTIATION_PROFILE_FIELDS] - replaced with the dynamically generated list of enabled fields and their descriptions
     * [ILP_DOCUMENT_CONTENT] - replaced with the content of the student pastoral record documents
     */
    private const string PROMPT_USER_PERSONA = "
        I will provide you with the content of a student learning plan and/or other supporting documentation. Your goal is to populate a JSON object according to the following target fields.

        Here is the document content to analyze:
        [ILP_DOCUMENT_CONTENT]

        Here are the target fields, including their current terminology and descriptions:

        [DIFFERENTIATION_PROFILE_FIELDS]

        Please extract the information and return a single JSON object in the following format. Ensure the output is valid JSON.

        ```json
        {
          \"overview\": \"<extracted_overview>\",
          \"interests_strengths\": \"<extracted_strengths>\",
          \"needs_challenges\": \"<extracted_needs>\",
          \"strategies_adjustments\": \"<extracted_strategies>\",
          \"goals\": \"<extracted_goals>\"
        }
        ```
    ";

    private const array RESPONSE_FORMAT = [
        'type'   => 'json_schema',
        'name'   => 'fields_format',
        'schema' => [
            'type'                 => 'object',
            'properties'           => [],
            'required'             => [],
            'additionalProperties' => false,
        ],
        'strict' => true
    ];

    /**
     * Constructor.
     *
     * @param string     $openAiEndpoint
     * @param string     $openAiModel
     * @param string     $azureDeploymentId
     * @param string     $azureDeploymentModel
     * @param string     $systemPrompt The system prompt from admin config
     * @param string     $reasoningEffort The reasoning effort level from admin config
     * @param string     $textVerbosity The text verbosity level from admin config
     * @param string     $documentContent
     * @param Collection $enabledFields
     */
    public function __construct(
        private string $openAiEndpoint,
        private string $openAiModel,
        private string $azureDeploymentId,
        private string $azureDeploymentModel,
        private string $systemPrompt,
        private string $reasoningEffort,
        private string $textVerbosity,
        private string $documentContent,
        private Collection $enabledFields,
    ) {
    }

    public function generate(): array
    {
        /* OpenAI uses the model name from our ui.
         * Azure uses the Deployment ID instead.
         */
        $model = $this->openAiModel;
        if (Helper::ENDPOINT_TYPE_AZURE === $this->openAiEndpoint) {
            $model = $this->azureDeploymentId;
        }

        $responseFormat = $this->getResponseFormat();

        /* Replacing the token with the config setting defined prompt
         */
        $systemPrompt = strtr(
            self::PROMPT_SYSTEM_PERSONA,
            [
                '[CONFIG_OPENAI_DIFFERENTIATION_SYSTEM_PROMPT]' => $this->systemPrompt,
            ]
        );

        $userPrompt = $this->generateUserPrompt();

        $payload = [
            'model'             => $model,
            'temperature'       => self::TEMPERATURE,
            'top_p'             => self::TOP_P,
            'max_output_tokens' => self::MAX_OUTPUT_TOKENS,
            'input'             => [
                [
                    'role'    => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role'    => 'user',
                    'content' => $userPrompt,
                ],
            ],
            'stream'            => false,
            'text'              => [
                'format' => $responseFormat,
            ],
        ];

        if ($this->textVerbosity !== Config::TEXT_VERBOSITY_OPTION_DEFAULT) {
            $payload['text']['verbosity'] = $this->textVerbosity;
        }

        if ($this->reasoningEffort !== Config::REASONING_EFFORT_OPTION_DEFAULT) {
            $payload['reasoning'] = ['effort' => $this->reasoningEffort];
        }

        return new ModelParser()->filterPayload($payload, $model, $this->openAiEndpoint);
    }

    /**
     * Generate the user prompt with dynamic fields
     * @return string
     */
    private function generateUserPrompt(): string
    {
        /* Build the fields prompt dynamically
         */
        $fieldsPrompt = $this->enabledFields->reduce(function (string $carry, Model $field) {
            $carry .= "- Field: " . $field->getName() . "\n"
                . "  - Terminology: \"" . $field->getTerminology() . "\"\n"
                . "  - Description: \"" . $field->getDescription() . "\"\n";
            return $carry;
        }, '');

        return strtr(
            self::PROMPT_USER_PERSONA,
            [
                '[ILP_DOCUMENT_CONTENT]' => $this->documentContent,
                '[DIFFERENTIATION_PROFILE_FIELDS]' => $fieldsPrompt,
            ]
        );
    }

    /**
     * Generate the response format and dynamically add the enabled fields and properties
     * @return array
     */
    private function getResponseFormat(): array
    {
        $responseFormat = self::RESPONSE_FORMAT;

        /* Dynamically build the response format based on enabled fields
         */
        $requiredFields = $this->enabledFields->reduce(function (array $carry, Model $field) {
            $key = $field->getName();
            $carry['required'][] = $key;
            $carry['properties'][$key] = [
                'type'        => 'string',
                'description' => 'USE HTML. ' . $field->getDescription(),
            ];
            return $carry;
        }, []);
        $responseFormat['schema']['required'] = $requiredFields['required'];
        $responseFormat['schema']['properties'] = $requiredFields['properties'];

        return $responseFormat;
    }
}

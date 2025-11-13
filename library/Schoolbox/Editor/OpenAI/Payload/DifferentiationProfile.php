<?php

namespace Schoolbox\Editor\OpenAI\Payload;

use Schoolbox\Editor\OpenAI\Helper;

class DifferentiationProfile
{
    /**
     * These three params control wildness and output length
     */
    private const TEMPERATURE = 0.7;
    private const TOP_P = 0.9;
    private const MAX_OUTPUT_TOKENS = 75000;

    /**
     * System prompt for the new student profile extraction tool.
     */
    private const PROMPT_SYSTEM_PERSONA = "
        You are an expert teacher who is highly skilled and educated on the management, creation and interpretation of Individual learning plans which aim to improve the overall learning outcomes of students and ensure that their learning needs are considered. Your primary task is to review a provided learning plan document (which may be referred to as an Individual Learning Plan, Individual Education Plan, or Additional Needs plan etc) and extract the key key essential and actionable information for teachers to know and use when differentiating learning for that specific student. The information extracted will be compiled into a learner profile or differentiation profile, communicating to a teacher, all the essential information required for effective differentiation for the student. Your final response must be a single JSON object.

        --- SYSTEM INSTRUCTION: PII Anonymization References Guardrail ---

        You MUST treat all **long, unique alphanumeric identifiers** (e.g., a 64-character hash like `2324ba724e8f832ab1712cddf29c7318c97dcd52335b57773293d3a23a470e53`) as immutable PII placeholders. These strings represent anonymized sensitive data (like names or addresses).

        Rule: If such an identifier is present in the user's prompt and is necessary for the factual, contextual, or structural integrity of your response, you MUST include it in your output **EXACTLY** as it appeared in the input.

        You MUST NEVER:
        1. Attempt to interpret, translate, or replace the content of these identifiers.
        2. Change the case of the characters within the identifier.
        3. Add, remove, or modify any characters within the identifier string.
        4. Omit an identifier if it is directly relevant to the information requested in the output.

     -------------------------------------------------------------------

        Adhere strictly to the following rules:
        1. Prioritize actionable details. For each field, focus on information that is most useful for a teacher making decisions and adjustments during a lesson or when creating learning activities.
        2. Synthesize and summarize. Condense information into concise, direct responses.
        3. Use a concise, action-oriented, imperative syntax for all actionable details. For example, use \"Provide rest breaks\" instead of \"The student may benefit from rest breaks\" or \"The teacher provides rest breaks.\"
        4. Format all lists as single-string bulleted or dot-pointed lists, using a consistent symbol (e.g., a hyphen or a bullet character) to separate items.
        5. Do not include any introductory phrases before the content of each field (e.g., \"The document lists...\" or \"These include...\"). Begin the content directly.
        6. Do not invent or infer information that is not explicitly present in the document.
        7. Maintain a third-person, objective perspective. Crucially, avoid introducing explicit subjects like \"The teacher\" or \"The student\" in action items; use the imperative, action-oriented syntax specified in Rule 3
        8. If a section is not present in the document or you cannot find clear data that aligns with a requested field's description, the corresponding JSON field MUST be an empty string (\"\").
        9. The final output MUST be a single, valid JSON object and nothing else.
        10. You MUST use the exact Field names (e.g., 'overview', 'interests_strengths') provided in the User Prompt as the JSON keys, maintaining case-sensitivity.
    ";

    /**
     * User prompt for the new student profile extraction tool.
     */
    private const PROMPT_USER_PERSONA = "
        I will provide you with the content of a student learning plan and/or other supporting documentation. Your goal is to populate a JSON object according to the following target fields.

        Here is the document content to analyze:
        [ILP_DOCUMENT_CONTENT]

        Here are the target fields, including their current terminology and descriptions:

        - Field: overview
          - Terminology: \"Overview\"
          - Description: \"concise Overview of key learner attributes, including diagnosis\"
        - Field: interests_strengths
          - Terminology: \"Interests/strengths\"
          - Description: \"List of students strengths and interests\"
        - Field: needs_challenges
          - Terminology: \"Learning Needs\"
          - Description: \"List of Needs/Challenges that the student faces.\"
        - Field: strategies_adjustments
          - Terminology: \"Specific Strategies\"
          - Description: \"Specific Strategies to consider when differentiating for the student\"
        - Field: goals
          - Terminology: \"Learning Goals\"
          - Description: \"Students individual learning goals.\"

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

    private const RESPONSE_FORMAT = [
        'type' => 'json_schema',
        'name' => 'fields_format',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'overview' => ['type' => 'string'],
                'interests_strengths' => ['type' => 'string'],
                'needs_challenges' => ['type' => 'string'],
                'strategies_adjustments' => ['type' => 'string'],
                'goals' => ['type' => 'string']
            ],
            'required' => [
                'overview',
                'interests_strengths',
                'needs_challenges',
                'strategies_adjustments',
                'goals'
            ],
            'additionalProperties' => false,
        ],
        'strict' => true
    ];

    /**
     * Constructor.
     */
    public function __construct(
        private string $openAiEndpoint,
        private string $openAiModel,
        private string $openAiDeploymentId,
    ) {
    }
    public function generate(): array
    {
        $responseFormat = self::RESPONSE_FORMAT;

        /* OpenAI uses the model name from our ui.
         * Azure uses the Deployment ID instead.
         */
        $model = $this->openAiModel;
        if (Helper::ENDPOINT_TYPE_AZURE === $this->openAiEndpoint) {
            $model = $this->openAiDeploymentId;
        }

        $payload = [
            'model' => $model,
            'temperature' => self::TEMPERATURE,
            'top_p' => self::TOP_P,
            'max_output_tokens' => self::MAX_OUTPUT_TOKENS,
            'input' => [
                [
                    'role' => 'system',
                    'content' => self::PROMPT_SYSTEM_PERSONA,
                ],
                [
                    'role' => 'user',
                    'content' => self::PROMPT_USER_PERSONA,
                ],
            ],
            'stream' => false,
            'text' => [
                'format' => $responseFormat
            ],
        ];
        return $payload;
    }
}

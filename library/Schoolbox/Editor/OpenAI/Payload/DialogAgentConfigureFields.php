<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Payload;

use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\Curriculum\Node;
use Schoolbox\Editor\OpenAI\Helper;
use Schoolbox\Admin\DialogAgent\Scenario\Model as Scenario;
use Schoolbox\Admin\DialogAgent\Scenario\Field\Model as Field;
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgent;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;

class DialogAgentConfigureFields
{
    /**
     * These three params control wildness and output length
     */
    private const float TEMPERATURE = 0.7;
    private const float TOP_P = 0.9;
    private const int MAX_OUTPUT_TOKENS = 75000;


    /**
     * System prompt for the agent
     */
    private const string PROMPT_SYSTEM_PERSONA = "
        You are an AI assistant helping educators configure a student discussion activity.

        Your role is to take the provided activity context and generate structured content for each scenario field.
        This output will later be used to build the prompt for a dialog agent that will hold a discussion with a student.

        You are not talking to the student.
        You are preparing configuration content for the system.

        You will be provided with:
            •	Course / Class context
            •	Activity title
            •	Activity description
            •	Scenario title
            •	Scenario details
            •	Curriculum codes
            •	Scenario fields, each with:
                •	Field name
                •	Field description (what this field is meant to contain)

        Your Task:
        For each scenario field, generate content that:
            •	Aligns strictly with the field's intent
            •	Reflects the learning goals implied by the activity
            •	Supports a discussion-based learning experience (not assessment)

        Output rules:
        Output plain text only
            •	You may use very light Markdown where helpful:
            •	Headings (###)
            •	Bullet points
            •	Numbered lists
            •	Do not include code blocks
            •	Do not include emojis
            •	Do not reference system behavior, models, or prompts
            •	Do not include explanations or commentary outside the requested fields
            •	Do not invent extra fields

        Guidance for Quality
            •	Write as if the content will be read by another AI and school teachers
            •	Be clear, explicit, and instructional where appropriate
            •	Prefer guidance over prescriptions
            •	If a field is about tone or behavior, describe it concretely
            •	If a field is about learning intent, connect it to curriculum and reasoning skills
            •	If a field is about constraints, be precise and enforceable
    ";


    /**
     * Hardcoded system prompt format for a given field
     * to be included inside the RESPONSE_FORMAT properties
     *
     * Placeholders:
     * [FIELD_TITLE]
     * [FIELD_META]
     */
    private const string PROMPT_SYSTEM_FIELD_CONTEXT = "FIELD TITLE: [FIELD_TITLE]\nFIELD DESCRIPTION:[FIELD_META]";


    /**
     * Hardcoded system prompt structure for context about the activity
     */
    private const string PROMPT_SYSTEM_ACTIVITY_CONTEXT = "
        Here is the learning activity information:
            Year Level: [YEAR_LEVEL]
            Class Name: [CLASS_NAME]
            Course Name: [COURSE_NAME]
            Unit Name: [UNIT_NAME]
            Curriculum: [CURRICULUM_CONTENT]
            Activity Title: [ACTIVITY_TITLE]
            Activity Description: [ACTIVITY_DESCRIPTION]
            Scenario Title: [SCENARIO_TITLE]
            Scenario Description: [SCENARIO_DESCRIPTION]
    ";


    /**
     * A structure that AI will use to format its output
     */
    private const array RESPONSE_FORMAT = [
        'type' => 'json_schema',
        'name' => 'fields_format',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'fields' => [
                    'type' => 'object',
                    'description' => 'A map of field ID to the value for that field, respond with only the value for each field, do not include the field name or description',
                    'properties' => [],
                    'required' => [],
                    'additionalProperties' => false,
                ],
                'openingMessage' => [
                    'type' => 'string',
                    'description' => 'An opening message from the perspective of the agent to start the discussion with the student',
                ],
            ],
            'required' => ['fields', 'openingMessage'],
            'additionalProperties' => false,
        ],
        'strict' => true
    ];


    /**
     * Constructor.
     *
     * @param string $openAiEndpoint
     * @param string $openAiModel
     * @param string $azureDeploymentId
     * @param string $azureDeploymentModel
     * @param string $featureSystemPrompt The system prompt from admin config
     * @param string $reasoningEffort The reasoning effort level from admin config
     * @param string $textVerbosity The text verbosity level from admin config
     */
    public function __construct(
        private string $openAiEndpoint,
        private string $openAiModel,
        private string $azureDeploymentId,
        private string $azureDeploymentModel,
        private string $featureSystemPrompt,
        private string $reasoningEffort,
        private string $textVerbosity,
    ) {
    }


    /**
     * Get the key of a particular field for the structured output part of the prompt
     *
     * @param Field $field
     * @return string
     */
    public static function getPayloadFieldKey(Field $field): string
    {
        return \sprintf("field_%d", $field->getId());
    }


    /**
     * Generate the payload to send to the AI
     *
     * @param Scenario $scenario
     * @param DialogAgent $assessment
     * @return array
     * @throws \JsonException
     */
    public function generate(Scenario $scenario, DialogAgent $assessment): array
    {
        /* OpenAI uses the model name from our ui.
         * Azure uses the Deployment ID instead.
         * Disgusting.
         */
        $payloadModel    = $this->openAiModel;
        $validationModel = $this->openAiModel;

        // ... but, we need the actual model in the Azure deployment
        // (or what we've been told it is) in order to filter the payload.
        if (Helper::ENDPOINT_TYPE_AZURE === $this->openAiEndpoint) {
            $payloadModel    = $this->azureDeploymentId;
            $validationModel = $this->azureDeploymentModel;
        }

        $payload = [
            /* An ordered list of instructions for OpenAi */
            'input' => [],
            /* Which variant of OpenAI to use (text model) */
            'model' => $payloadModel,
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
                'format' => $this->generateResponseFormat($scenario),
            ],
        ];

        if ($this->textVerbosity !== Config::TEXT_VERBOSITY_OPTION_DEFAULT) {
            $payload['text']['verbosity'] = $this->textVerbosity;
        }

        if ($this->reasoningEffort !== Config::REASONING_EFFORT_OPTION_DEFAULT) {
            $payload['reasoning'] = ['effort' => $this->reasoningEffort];
        }

        /* Add in the overarching feature system prompt (from config) if available */
        if ('' !== trim($this->featureSystemPrompt)) {
            $payload['input'][] = [
                'role' => 'system',
                'content' => $this->featureSystemPrompt,
            ];
        }

        /* The specific system prompt about what to generate */
        $payload['input'][] = [
            'role' => 'system',
            'content' => self::PROMPT_SYSTEM_PERSONA,
        ];

        /* Include the activity context */
        $payload['input'][] = [
            'role' => 'user',
            'content' => $this->formatActivityContextPrompt($scenario, $assessment),
        ];

        return new ModelParser()->filterPayload($payload, $validationModel);
    }


    /**
     * Generate the response format portion of the payload,
     * this determines what the AI will ultimately respond with
     *
     * @param Scenario $scenario
     * @return array
     */
    private function generateResponseFormat(Scenario $scenario): array
    {
        $responseFormat = self::RESPONSE_FORMAT;

        foreach ($scenario->getFields() as $field) {
            $key = $this->getPayloadFieldKey($field);

            /* TODO: Handle $field->getType() (textarea, switch, dropdown)
             * We don't use or support anything but textarea in 26.0, but the schema exists for them to be added in the future...
             */

            $responseFormat['schema']['properties']['fields']['properties'][$key] = [
                'type' => 'string',
                'description' => strtr(self::PROMPT_SYSTEM_FIELD_CONTEXT, [
                    '[FIELD_TITLE]' => $field->getTitle(),
                    '[FIELD_META]' => $field->getMeta(),
                ]),
            ];

            /* strict mode, so all property keys must be required  */
            $responseFormat['schema']['properties']['fields']['required'][] = $key;
        }

        return $responseFormat;
    }


    /**
     * Generate the system prompt for the activity context
     * @param Scenario $scenario
     * @param DialogAgent $assessment
     * @return string
     */
    private function formatActivityContextPrompt(Scenario $scenario, DialogAgent $assessment): string
    {
        $baseContext = $assessment->getContext()->getBaseContext();
        $unit = $baseContext instanceof UnitImplementation ? $baseContext : null;

        $curriculumNodes = $assessment->getCurriculumNodes()->map(fn(Node\Model $node) => [
            'code' => $node->getCode(),
            'name' => $node->getName(),
            'description' => \Scrub::html2text($node->getDescription()),
        ]);

        return strtr(
            self::PROMPT_SYSTEM_ACTIVITY_CONTEXT,
            [
                '[YEAR_LEVEL]' => implode(',', $assessment->getBaseContext()->getFolder()->getYearLevels()?->getPropertyValues('name') ?? []),
                '[CLASS_NAME]' => $assessment->getContext()->getFolder()->getName(),
                '[COURSE_NAME]' => $unit?->getSpecification()->getCourse()->getTitle() ?? "N/A",
                '[UNIT_NAME]' => $unit?->getTitle() ?? "N/A",
                '[CURRICULUM_CONTENT]' => json_encode($curriculumNodes, JSON_PRETTY_PRINT),
                '[ACTIVITY_TITLE]' => $assessment->getTitle(),
                '[ACTIVITY_DESCRIPTION]' => \Scrub::html2text($assessment->getDescription()),
                '[SCENARIO_TITLE]' => $scenario->getTitle(),
                '[SCENARIO_DESCRIPTION]' => \Scrub::html2text($scenario->getDescription()),
            ]
        );
    }
}

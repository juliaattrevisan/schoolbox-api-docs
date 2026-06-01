<?php

namespace Schoolbox\Editor\OpenAI\Payload;

use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\Curriculum\Node\Mapper;
use Schoolbox\Editor\OpenAI\Helper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

class LessonPlan
{
    /**
     * These three params control wildness and output length
     */
    private const float TEMPERATURE = 0.7;
    private const float TOP_P = 0.9;
    private const int MAX_OUTPUT_TOKENS = 20000;


    /**
     * This seems mostly the same across most usages, other than specifying that context
     * (some parts stolen directly from openai's docs)
     */
    private const string PROMPT_SYSTEM_PERSONA_MULTIPLE = "
        You are an expert teacher, proficient in creating engaging, well-developed, and effective learning and teaching
        plans for your students. You will receive a plan type that is part of a specific course and unit within a defined
        curriculum area. Your task is to develop a plan that addresses the provided curriculum content and incorporates
        any additional notes to ensure strong alignment. Your language of instruction is [LANGUAGE].

        If any field is missing or left blank, follow these guidelines:
            * Course/Unit Context: If missing, infer a general context using the curriculum content and plan type.
            * Curriculum Content: If missing, infer appropriate content based on the plan type, course/unit context, title, and any additional notes.
            * Curriculum Area: If missing, use the plan type, course/unit context, title, and additional notes to determine the relevant subject area.
            * Duration: If not provided, exclude any time-specific content or scheduling details from the plan.
            * Additional Notes: If missing, proceed without them and ensure the plan aligns closely with the plan type, curriculum content, title, course/unit context, and curriculum area.

        Create a range of suggestions USING HTML that align with the following field topics; each field topic should
        use the fields_format json response format.
    ";


    /**
     * This is the same as above, but for regenerating a single field only
     */
    private const string PROMPT_SYSTEM_PERSONA_SINGLE = "
        You are an expert teacher, proficient in creating engaging, well-developed, and effective learning and teaching
        plans for your students. You will receive a plan type that is part of a specific course and unit within a defined
        curriculum area. Your task is to regenerate a single specified field in a teaching plan, based on the full context
        of the other fields already completed. This field should align with the plan's overall structure, teaching intent,
        and curriculum requirements.

        You will receive all fields of the current plan, including the one that is being regenerated. Use the existing
        content across the plan as contextual guidance to ensure the regenerated field is pedagogically sound,
        consistent with the plan flow, and aligned to the curriculum.

        Regenerate only the requested field. Do not alter or repeat any of the other fields.
        Your language of instruction is [LANGUAGE].

        If any field is missing or left blank, follow these guidelines:
            * Course/Unit Context: If missing, infer a general context using the curriculum content, plan type and context from existing fields.
            * Curriculum Content: If missing, infer appropriate content based on the plan type, course/unit context, title, any additional notes and context from existing fields.
            * Curriculum Area: If missing, use the plan type, course/unit context, title, additional notes and context from existing fields to determine the relevant subject area.
            * Duration: If not provided, exclude any time-specific content or scheduling details from the plan.
            * Additional Notes: If missing, proceed without them and ensure the plan aligns closely with the plan type, curriculum content, title, course/unit context,curriculum area and content from existing fields.

        Create a range of suggestions USING HTML that align with the following field topics; each field topic should
        use the fields_format json response format.
    ";


    /**
     * Something that shapes what the ai would make;
     * provide additional instructions (if meaningful)
     */
    private const string PROMPT_USER_PERSONA_MULTIPLE = "
        Plan Type: [TEMPLATE TITLE]
        Title: [LESSON PLAN TITLE]
        Course/Unit Context: [CONTEXT]
        [DURATION]
        [CURRICULUM CONTENT]
        [CURRICULUM AREA]
        [ADDITIONAL NOTES]
    ";


    /**
     * This is the same as above, but for regenerating a single field only
     */
    private const string PROMPT_USER_PERSONA_SINGLE = "
        Field to Regenerate: [CURRENT FIELD TITLE]

        Plan Type: [TEMPLATE TITLE]
        Title: [LESSON PLAN TITLE]
        Course/Unit Context: [CONTEXT]
        [DURATION]
        [CURRICULUM CONTENT]
        [CURRICULUM AREA]
        [ADDITIONAL NOTES]

        [OTHER FIELDS]
    ";


    /**
     * A structure that AI is supposed to obey, but...I don't think it cares
     */
    private const array RESPONSE_FORMAT = [
        'type' => 'json_schema',
        'name' => 'fields_format',
        'schema' => [
            'type' => 'object',
            'properties' => [],
            'required' => [],
            'additionalProperties' => false,
        ],
        'strict' => true
    ];


    /**
     * Constructor.
     *
     * OpenAI uses the openAiModel in the payload, for the 'model' node.
     * Azure uses the openAiDeploymentId in the payload, for the 'model' node.
     *
     * @param string $openAiEndpoint
     * @param string $openAiModel
     * @param string $azureDeploymentId
     * @param string $azureDeploymentModel
     * @param string $textSystemPrompt
     * @param string $reasoningEffort The reasoning effort level from admin config
     * @param string $textVerbosity The text verbosity level from admin config
     * @param string $language
     * @param Mapper $curriculumNodeMapper
     */
    public function __construct(
        private string $openAiEndpoint,
        private string $openAiModel,
        private string $azureDeploymentId,
        private string $azureDeploymentModel,
        private string $textSystemPrompt,
        private string $reasoningEffort,
        private string $textVerbosity,
        private string $language,
        private \Schoolbox\Curriculum\Node\Mapper $curriculumNodeMapper
    ) {
    }


    /**
     * Get the key of a field for the structured output part of the prompt
     * To enhance the behaviour of the AI, we desire to have the field title itself within each key,
     * however to ensure multiple fields of the same title can be used, we also append the field id if necessary
     *
     * @param array $fields All fields that are to be generated
     * @param int|string $fieldId The ID of the field to retrieve the key for
     * @return string
     */
    public function getPayloadFieldKey(array $fields, int|string $fieldId): string
    {
        $fieldCounts = [];
        foreach ($fields as $field) {
            $fieldTitle = trim((string)$field['title']);

            if (!isset($fieldCounts[$fieldTitle])) {
                $fieldCounts[$fieldTitle] = 0;
            }

            $fieldCounts[$fieldTitle]++;

            if ($field['id'] === $fieldId) {
                $fieldKey = $fieldTitle;
                // only append the number if we actually need to
                if ($fieldCounts[$fieldTitle] > 1) {
                    $fieldKey .= ' ' . $fieldCounts[$fieldTitle];
                }
                return $fieldKey;
            }
        }

        throw new \InvalidArgumentException('Field ID not found in fields array');
    }


    /**
     * Generate details for a given field for the prompt.
     * Used for the user persona as well as the structured output contents
     *
     * @param array $field
     * @return string
     */
    private function generateFieldDetails(array $field): string
    {
        $fieldId = (string)$field['id'];
        $fieldTitle = trim((string)$field['title']);
        $fieldDescription = trim((string)$field['description']);
        $fieldBody = trim((string)$field['body']);
        $fieldTemplate = trim((string)$field['template']);

        /* Add a visibility statement
         */
        if (trim((string)$field['visibility']) === \Schoolbox\Role::STUDENT) {
            $fieldVisibility = 'This field is visible to students and staff.';
        } else {
            $fieldVisibility = 'This field is visible to staff.';
        }

        $details = sprintf(
            "FIELD TITLE: %s\nFIELD DESCRIPTION: %s\nVISIBILITY: %s",
            $fieldTitle,
            $fieldDescription,
            $fieldVisibility
        );

        /* If the body/template is present, tack it on the end
         */
        if ('' !== $fieldBody) {
            $details .= sprintf("\nCURRENT VALUE: %s", $fieldBody);
        }
        if ('' !== $fieldTemplate) {
            $details .= sprintf("\nTEMPLATE: %s", $fieldTemplate);
        }

        return $details;
    }


    /**
     * Derive prompt attributes from an existing Lesson Plan and Lesson Plan Template.
     *
     * This lets you supply scalar ids, which are resolved to actual instances via mappers
     *
     * @param string $templateTitle
     * @param string $lessonPlanTitle
     * @param string $context
     * @param array $fields
     * @param string $duration
     * @param array $curriculumNodeIds
     * @param string $additionalContext
     * @param ?string $generateForField
     * @return array
     * @throws Deleted
     * @throws Exception
     */
    public function generate(
        string $templateTitle,
        string $lessonPlanTitle,
        string $context,
        array $fields,
        string $duration,
        array $curriculumNodeIds,
        string $additionalContext = '',
        ?string $generateForField = null
    ): array {
        /* Details about how to use each field
         */
        $responseFormat = self::RESPONSE_FORMAT;
        $responseFormat = $this->augmentProperties($responseFormat, $fields, $duration);

        /* This is currently a node's full ancestry, indexed by the node id
         */
        $curriculumNames = $this->compileNodeTitles($this->curriculumNodeMapper->findByColumn('id', $curriculumNodeIds));

        /* Generate the Systema nd User nodes for the prompt payload
         */
        $prompt = $this->generatePrompt(
            $templateTitle,
            $lessonPlanTitle,
            $context,
            $duration,
            $curriculumNames,
            $fields,
            $generateForField,
            $additionalContext,
        );

        $payload = [
            /* An ordered list of instructions for OpenAi
             */
            "input" => [
                [
                    "role" => "system",
                    "content" => $prompt['system'],
                ],
                [
                    "role" => "user",
                    "content" => $prompt['user'],
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
            /* Lesson plans don't support streaming as they need the formatted json to be correct
             */
            "stream" => false,
            'text' => [
                'format' => $responseFormat,
            ],
        ];

        if ($this->textVerbosity !== Config::TEXT_VERBOSITY_OPTION_DEFAULT) {
            $payload['text']['verbosity'] = $this->textVerbosity;
        }

        if ($this->reasoningEffort !== Config::REASONING_EFFORT_OPTION_DEFAULT) {
            $payload['reasoning'] = ['effort' => $this->reasoningEffort];
        }

        /* Add in the overarching text system prompt if available
         */
        if ('' !== $this->textSystemPrompt) {
            array_unshift($payload['input'], [
                'role' => 'system',
                'content' => $this->textSystemPrompt,
            ]);
        }

        return new PayloadFinaliser()->finalise(
            $payload,
            $this->openAiEndpoint,
            $this->openAiModel,
            $this->azureDeploymentId,
            $this->azureDeploymentModel,
        );
    }


    /**
     * Generate a prompt using Luisa Words, using the prompt attributes directly from the args.
     * These could come from a POST array or similar; generateFromInstances() lets you supply
     * actual Lesson Plan and Lesson Plan Template instances, saving you extracting this manually.
     *
     * @param string $templateTitle
     * @param string $lessonPlanTitle
     * @param string $context
     * @param string $duration
     * @param array $curriculumNames
     * @param array $fields
     * @param string|null $generateForField
     * @param string $additionalContext
     * @return array
     */
    private function generatePrompt(
        string $templateTitle,
        string $lessonPlanTitle,
        string $context,
        string $duration,
        array $curriculumNames,
        array $fields,
        ?string $generateForField = null,
        string $additionalContext = ''
    ): array {
        /* Use the right base prompt depending on if we're regenerating a single field
         * or multiple fields at once
         */
        if (null !== $generateForField) {
            $systemContent = self::PROMPT_SYSTEM_PERSONA_SINGLE;
            $userContent = self::PROMPT_USER_PERSONA_SINGLE;
        } else {
            $systemContent = self::PROMPT_SYSTEM_PERSONA_MULTIPLE;
            $userContent = self::PROMPT_USER_PERSONA_MULTIPLE;
        }

        /* Conduct replacements
         */
        $systemContent = strtr($systemContent, [
            '[LANGUAGE]' => $this->language
        ]);

        $userContent = strtr($userContent, [
            '[TEMPLATE TITLE]' => $templateTitle,
            '[LESSON PLAN TITLE]' => $lessonPlanTitle,
            '[CONTEXT]' => $context,
            '[DURATION]' => '' !== $duration ? 'Duration: ' . $duration : '',
            '[ADDITIONAL NOTES]' => '' !== $additionalContext ? 'Additional Notes: ' . $additionalContext : '',
        ]);

        if (null !== $generateForField) {
            $otherFieldsInfo = "Other Fields:";
            $currentField = null;

            foreach ($fields as $field) {
                if ((string)$field['id'] === (string)$generateForField) {
                    $currentField = $field; // since $fields is not indexed by id
                    continue;
                }

                $fieldDetails = $this->generateFieldDetails($field);
                $otherFieldsInfo .= sprintf("\n * %s", $fieldDetails);
            }

            $userContent = strtr($userContent, [
                '[CURRENT FIELD TITLE]' => $currentField['title'],
                '[OTHER FIELDS]' => $otherFieldsInfo
            ]);
        } else {
            $userContent = strtr($userContent, [
                '[CURRENT FIELD TITLE]' => '',
                '[OTHER FIELDS]' => ''
            ]);
        }

        if (\count($curriculumNames)) {
            /* Yoink out the node's name
             */
            $nodes = [];

            foreach ($curriculumNames as $fieldId => &$vals) {
                $nodes[] = array_pop($vals);
            }

            unset($vals);

            $curriculumContent = implode(",\n", $nodes);

            /* Yoink out the rest of each node's hierarchy, dedupe it, and then compile it into a csv
             * Note that multiple hierarchies might exist in this cauldron of data
             */
            $areas = [];

            foreach ($curriculumNames as $fieldId => $vals) {
                $areas = array_merge($vals);
            }

            /* Slam it into the prompt, in a particular order I suppose
             */
            $curriculumAreas = implode(', ', $areas);

            $userContent = strtr($userContent, [
                '[CURRICULUM CONTENT]' => 'Curriculum Content: ' . $curriculumContent,
                '[CURRICULUM AREA]' => 'Curriculum Area: ' . $curriculumAreas
            ]);
        } else {
            $userContent = strtr($userContent, [
                '[CURRICULUM CONTENT]' => '',
                '[CURRICULUM AREA]' => ''
            ]);
        }

        return [
            'system' => trim($systemContent),
            'user' => trim($userContent)
        ];
    }


    /**
     * Compose the prompt piece that talks about each Lesson Plan Template Field
     * and its contents. These end up in the Properties node in the response format
     *
     * @param array $responseFormat
     * @param array $fields
     * @param string $duration
     * @return array
     */
    private function augmentProperties(array $responseFormat, array $fields, string $duration): array
    {
        $struct = $responseFormat;

        /* Find some details about how to use the template fields
         */
        foreach ($fields as $field) {
            $fieldSelected = (bool)$field['selected'];

            /* Skip past this field: user didn't want to update it
             */
            if (!$fieldSelected) {
                continue;
            }

            $fieldKey = $this->getPayloadFieldKey($fields, $field['id']);
            $details = $this->generateFieldDetails($field);


            /* Make a giant prompt slab for each field
             */
            $struct['schema']['properties'][$fieldKey] = [
                'type' => 'string',
                'description' => sprintf("USE HTML\n%s", $details),
            ];

            /* I think this forces the $fieldId to spawn in the json payload
             * that openai gives back to us
             */
            $struct['schema']['required'][] = $fieldKey;
        }

        return $struct;
    }


    /* Curriculum Nodes
     */

    /**
     * Collect something human-readable via the node identifiers:
     * Compile all the ancestor nodes, right back to the root level
     *
     * @param GenericCollection $nodes
     * @return array
     * @throws Exception
     * @throws Deleted
     */
    private function compileNodeTitles(GenericCollection $nodes): array
    {
        $results = [];

        $nodeIds = $nodes->indexBy('id')->keys();

        foreach ($nodeIds as $nodeId) {
            $results[] = $this->collate($nodeId, [], true);
        }

        $stack = [];

        /* Find the last node in the list and grab its id because we'll index its entire hierarchy
         * against it, resulting in basically a list of nodes right to the top, like this:
         *
            Victorian Curriculum v2.0
            Learning areas
            Science
            Science Understanding
            Earth and space sciences
            Levels 7 and 8
            key processes of the rock cycle occur over different timescales........
         */
        foreach ($results as $result) {
            $lastNodeId = $result[\count($result) - 1]->getId();

            foreach ($result as $node) {
                $stack[$lastNodeId][] = $node->getName();
            }
        }

        return $stack;
    }


    /**
     * Recursively obtain the ancestry of a curriculum node
     *
     * @param int $nodeId
     * @param array $stack
     * @param bool $first
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    private function collate(int $nodeId, array $stack, bool $first = false): array
    {
        /* These nodes are only partially hydrated, at best:
         * Parent nodes don't know THEIR parent nodes.
         * Also, this services the scalar payload approach where you just know curric ids
         *
         * TODO: Maybe update curric node mapper's hydration
         */
        $currentNode = $this->curriculumNodeMapper->findById($nodeId);
        $parentNode = $this->curriculumNodeMapper->findById($currentNode->getParent()->getId());

        $parentNode->getChildren()->setOffset($currentNode->getId(), $currentNode);

        if ($first) {
            $stack[] = $currentNode;
        }

        $stack[] = $parentNode;

        if ($parentNode->getParent()?->getId()) {
            return $this->collate($parentNode->getId(), $stack);
        }

        return array_reverse($stack);
    }
}

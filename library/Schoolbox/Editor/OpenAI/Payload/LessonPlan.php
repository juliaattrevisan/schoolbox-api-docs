<?php

namespace Schoolbox\Editor\OpenAI\Payload;

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
    private const TEMPERATURE = 0.7;
    private const TOP_P = 0.9;
    private const MAX_OUTPUT_TOKENS = 20000;


    /**
     * This seems mostly the same across most usages, other than specifying that context
     * (some parts stolen directly from openai's docs)
     */
    private const PROMPT_SYSTEM_PERSONA = "
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
     * A structure that AI is supposed to obey, but...I don't think it cares
     */
    private const RESPONSE_FORMAT = [
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
     * @param string $openAiDeploymentId
     * @param string $language
     * @param Mapper $curriculumNodeMapper
     */
    public function __construct(
        private string $openAiEndpoint,
        private string $openAiModel,
        private string $openAiDeploymentId,
        private string $language,
        private \Schoolbox\Curriculum\Node\Mapper $curriculumNodeMapper
    ) {
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
     * @return string
     * @throws Deleted
     * @throws Exception
     * @throws \JsonException
     */
    public function generate(
        string $templateTitle,
        string $lessonPlanTitle,
        string $context,
        array $fields,
        string $duration,
        array $curriculumNodeIds,
        string $additionalContext = ''
    ): string {
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
            $additionalContext
        );

        /* OpenAI uses the model name from our ui.
         * Azure uses the Deployment ID instead.
         * Disgusting.
         */
        $model = $this->openAiModel;

        if (Helper::ENDPOINT_TYPE_AZURE === $this->openAiEndpoint) {
            $model = $this->openAiDeploymentId;
        }

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
            /* Which variant of OpenAI to use
             * Each has their own way of producing results in varying ways
             * (pick one)
             */
            "model" => $model,
            /* A kind of OpenAI currency to use in this request
             */
            "max_output_tokens" => self::MAX_OUTPUT_TOKENS,
            /* How "creative" the OpenAI response will be
             * The bigger the number, the more creative it gets
             * Goes between 0 and 2
             */
            "temperature" => self::TEMPERATURE,
            "top_p" => self::TOP_P,
            /* For futureproofing: never really used atm
             */
            "stream" => false,
            'text' => [
                'format' => $responseFormat
            ]
        ];

        new \Alaress_Probe($payload);die();
        return json_encode($payload, JSON_THROW_ON_ERROR);
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
     * @param string $additionalContext
     * @return array
     */
    private function generatePrompt(
        string $templateTitle,
        string $lessonPlanTitle,
        string $context,
        string $duration,
        array $curriculumNames,
        string $additionalContext = ''
    ): array {
        /* Conduct replacements
         */
        $systemContent = strtr(self::PROMPT_SYSTEM_PERSONA, [
            '[LANGUAGE]' => $this->language
        ]);

        /* Something that shapes what the ai would make;
         * provide additional instructions (if meaningful)
         */
        $userContent = "
            Plan Type: [TEMPLATE TITLE]
            Title: [LESSON PLAN TITLE]
            Course/Unit Context: [CONTEXT]
            Duration: [DURATION]
            [CURRICULUM CONTENT]
            [CURRICULUM AREA]
        ";

        $userContent = strtr($userContent, [
            '[TEMPLATE TITLE]' => $templateTitle,
            '[LESSON PLAN TITLE]' => $lessonPlanTitle,
            '[CONTEXT]' => $context,
            '[DURATION]' => $duration,
        ]);

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

        /* Whatever the user also instructed from the ui
         */
        if ('' !== $additionalContext) {
            $userContent .= '    Additional Notes: ' . $additionalContext;
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

            $fieldId = (string)$field['id'];
            $fieldTitle = trim((string)$field['title']);
            $fieldDescription = trim((string)$field['description']);
            $fieldBody = trim((string)$field['body']);

            /* Add a visibility statement
             */
            if (trim((string)$field['visibility']) === \Schoolbox\Role::STUDENT) {
                $fieldVisibility = 'This field is visible to students and staff.';
            } else {
                $fieldVisibility = 'This field is visible to staff.';
            }

            /* Make a giant prompt slab for each field
             */
            $struct['schema']['properties'][$fieldTitle] = [
                'type' => 'string',
                'description' => sprintf(
                    "USE HTML\nFIELD TITLE: %s\nFIELD DESCRIPTION: %s\nTEMPLATE: %s\nVISIBILITY: %s",
                    $fieldTitle,
                    $fieldDescription,
                    $fieldBody,
                    $fieldVisibility
                ),
            ];

            /* I think this forces the $fieldId to spawn in the json payload
             * that openai gives back to us
             */
            $struct['schema']['required'][] = $fieldTitle;
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

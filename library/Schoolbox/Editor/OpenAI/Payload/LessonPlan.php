<?php

namespace Schoolbox\Editor\OpenAI\Payload;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

class LessonPlan
{
    /**
     * These two params control wildness
     */
    private const TEMPERATURE = 1;
    private const TOP_P = 1;


    /**
     * This seems mostly the same across most usages, other than specifying that context
     * (some parts stolen directly from openai's docs)
     */
    private const PROMPT_SYSTEM_PERSONA = "
        You are an expert \"[CONTEXT]\" teacher for a class, proficient in creating engaging, 
        well-developed and effective lesson plans for your students. Each lesson should be engaging and
        appropriate for \"[CONTEXT]\" students.
        
        Create a range of suggestions USING HTML that align with the following field topics; each field topic should
        use the fields_format json response format.

        Your entire response/output is going to consist of a single JSON object {}, and you will NOT 
        wrap it within JSON md markers.
    ";


//    /**
//     * Additional instructions specified by the user
//     */
//    private const PROMPT_TEMPLATE_ADDITIONAL_CONTEXT = '
//        Additional Notes: %s
//    ';


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
     * @param string $openAiModel
     * @param \Schoolbox\Curriculum\Node\Mapper $curriculumNodeMapper
     */
    public function __construct(
        private string $openAiModel,
        private \Schoolbox\Curriculum\Node\Mapper $curriculumNodeMapper
    ) {
    }


    /**
     * Derive prompt attributes from an existing Lesson Plan and Lesson Plan Template.
     *
     * This lets you supply scalar ids, which are resolved to actual instances via mappers
     *
     * @param string $context
     * @param array $fields
     * @param array $curriculumNodeIds
     * @param string $additionalContext
     * @return string
     * @throws Deleted
     * @throws Exception
     * @throws \JsonException
     */
    public function generate(
        string $context,
        array $fields,
        array $curriculumNodeIds,
        string $additionalContext = ''
    ): string {
        /* Details about how to use each field
         */
        $responseFormat = self::RESPONSE_FORMAT;
        $responseFormat = $this->augmentProperties($responseFormat, $fields);

        /* TODO This is currently a node's full ancestry, index by the node id
         *  This goes into the payload in a different way than a string injection
         *  so .........figure that out tomorrow
         */
        $curriculumNames = $this->compileNodeTitles($this->curriculumNodeMapper->findByColumn('id', $curriculumNodeIds));

        $prompt = $this->generatePrompt(
            $context,
            $curriculumNames,
            $additionalContext
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
            /* Which variant of OpenAI to use
             * Each has their own way of producing results in varying ways
             * (pick one)
             */
            "model" => $this->openAiModel,
            /* A kind of OpenAI currency to use in this request
             */
            "max_output_tokens" => 2000,
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

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }


    /**
     * Generate a prompt using Luisa Words, using the prompt attributes directly from the args.
     * These could come from a POST array or similar; generateFromInstances() lets you supply
     * actual Lesson Plan and Lesson Plan Template instances, saving you extracting this manually.
     *
     * @param string $context
     * @param array $curriculumNames
     * @param string $additionalContext
     * @return array
     */
    private function generatePrompt(
        string $context,
        array $curriculumNames,
        string $additionalContext = ''
    ): array {
        /* Conduct replacements
         */
        $systemContent = strtr(self::PROMPT_SYSTEM_PERSONA, [
            '[CONTEXT]' => $context,
        ]);

        /* Something that shapes what the ai would make;
         * provide additional instructions (if meaningful)
         */
        $userContent = '';
//
//        if (\count($curriculumNames)) {
//            $userContent = "Create lesson plans aligned to the curriculum areas listed below.\n\n";
//
//            foreach ($curriculumNames as $fieldId => $val) {
//                $userContent .= $val;
//            }
//        }
//
//        if ('' !== $additionalContext) {
//            $userContent .= sprintf(self::PROMPT_TEMPLATE_ADDITIONAL_CONTEXT, $additionalContext);
//        }

        return [
            'system' => $systemContent,
            'user' => $userContent
        ];
    }


    /**
     * Compose the prompt piece that talks about each Lesson Plan Template Field
     * and its contents. These end up in the Properties node in the response format
     *
     * @param array $responseFormat
     * @param array $fields
     * @return array
     */
    private function augmentProperties(array $responseFormat, array $fields): array
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
            $fieldVisibility = trim((string)$field['visibility']);
            $fieldBody = trim((string)$field['body']);

            $struct['schema']['properties'][$fieldId] = [
                'type' => 'string',
                'description' => sprintf(
                    "FIELD TITLE: %s\nFIELD DESCRIPTION: %s\nTEMPLATE: %s",
                    $fieldTitle,
                    $fieldDescription,
                    $fieldBody
                ),
            ];

            $struct['schema']['required'][] = $fieldId;
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

            $str = '';

            foreach ($result as $node) {
                $str .= sprintf(
                    "%s\n",
                    $node->getName()
                );
            }

            $stack[$lastNodeId] = $str;
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

<?php

namespace Schoolbox\Editor\OpenAI\Payload;

use DebugBar\DebugBarException;
use Schoolbox\Admin\LessonPlan\Template\Field\Mapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\LessonPlan\Model;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

class LessonPlan
{
    /**
     * These two params control wildness
     */
    private const TEMPERATURE = 0.0;
    private const TOP_P = 0.0;


    /**
     * This seems mostly the same across most usages, other than specifying that context
     * (some parts stolen directly from openai's docs)
     */
    private const PROMPT_SYSTEM_PERSONA = "
        You are a qualified teacher looking to generate a lesson plan for year 9 science.
        
        Your entire response/output is going to consist of a single JSON object {}, and you will NOT 
        wrap it within JSON md markers.
    ";


    /**
     * Additional instructions specified by the user
     */
    private const PROMPT_TEMPLATE_ADDITIONAL_CONTEXT = '
        Additional Notes: %s
    ';


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
     * @param Mapper $lessonPlanTemplateFieldMapper
     * @param \Schoolbox\Curriculum\Node\Mapper $curriculumNodeMapper
     */
    public function __construct(
        private string $openAiModel,
        private \Schoolbox\Admin\LessonPlan\Template\Field\Mapper $lessonPlanTemplateFieldMapper,
        private \Schoolbox\Curriculum\Node\Mapper $curriculumNodeMapper
    ) {
    }


    /**
     * Derive prompt attributes from an existing Lesson Plan and Lesson Plan Template.
     *
     * This lets you supply already pre-hydrated object instances
     *
     * @param Model $lessonPlan
     * @param GenericCollection $lessonPlanTemplateFields
     * @param string $additionalContext
     * @return string
     * @throws Deleted
     * @throws Exception
     * @throws \JsonException
     */
    public function generateFromInstances(
        \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan,
        GenericCollection $lessonPlanTemplateFields,
        string $additionalContext = ''
    ): string {
        /* wow
         * Get some info about the various contexts in which this lesson plan exists
         * such as the over-arching course name, unit name, and folder name
         */
        $context = sprintf(
            '%s %s',
            $lessonPlan->getSpecificationAssessment()->getBaseContext()->getFolder()->getUnitSpecification()->getCourse()->getFolder()->getName(),
            $lessonPlan->getContext()->getTitle(),
        );

        $folder = $lessonPlan->getContext()->getFolder()->getName();

        /* Details about how to use each field
         */
        $responseFormat = self::RESPONSE_FORMAT;
        $responseFormat = $this->augmentProperties($responseFormat, $lessonPlanTemplateFields);

        /* Find some curriculum node names, if any were chosen
         */
        $curriculum = '';

        /* TODO This is currently a node's full ancestry, index by the node id
         *  This goes into the payload in a different way than a string injection
         *  so .........figure that out tomorrow
         */
        $curriculumNames = $this->compileNodeTitles($lessonPlan->getCurriculumNodes());

        $prompt = $this->generatePrompt(
            $context,
            $folder,
            $curriculumNames,
            $additionalContext
        );

        return $this->generate($prompt['system'], $prompt['user'], $responseFormat);
    }


    /**
     * Derive prompt attributes from an existing Lesson Plan and Lesson Plan Template.
     *
     * This lets you supply scalar ids, which are resolved to actual instances via mappers
     *
     * @param string $context
     * @param string $folder
     * @param array $lessonPlanTemplateFieldIds
     * @param array $curriculumNodeIds
     * @param string $additionalContext
     * @return string
     * @throws DebugBarException
     * @throws Deleted
     * @throws Exception
     * @throws \JsonException
     */
    public function generateFromScalars(
        string $context,
        string $folder,
        array $lessonPlanTemplateFieldIds,
        array $curriculumNodeIds,
        string $additionalContext = ''
    ): string {
        /* Details about how to use each field
         */
        $responseFormat = self::RESPONSE_FORMAT;
        $responseFormat = $this->augmentProperties($responseFormat, $this->lessonPlanTemplateFieldMapper->findByColumn('id', $lessonPlanTemplateFieldIds));

        /* Find some curriculum node names, if any were chosen
         */
        $curriculum = '';

        /* TODO This is currently a node's full ancestry, index by the node id
         *  This goes into the payload in a different way than a string injection
         *  so .........figure that out tomorrow
         */
        $curriculumNames = $this->compileNodeTitles($this->curriculumNodeMapper->findByColumn('id', $curriculumNodeIds));

        $prompt = $this->generatePrompt(
            $context,
            $folder,
            $curriculumNames,
            $additionalContext
        );

        return $this->generate($prompt['system'], $prompt['user'], $responseFormat);
    }


    /**
     * Generate a prompt using Luisa Words, using the prompt attributes directly from the args.
     * These could come from a POST array or similar; generateFromInstances() lets you supply
     * actual Lesson Plan and Lesson Plan Template instances, saving you extracting this manually.
     *
     * @param string $context
     * @param string $folder
     * @param array $curriculumNames
     * @param string $additionalContext
     * @return array
     */
    private function generatePrompt(
        string $context,
        string $folder,
        array $curriculumNames,
        string $additionalContext = ''
    ): array {
        /* Conduct replacements
         */
        $systemContent = strtr(self::PROMPT_SYSTEM_PERSONA, [
            '[CONTEXT]' => $context,
            '[FOLDER]' => $folder,
        ]);

        /* Something that shapes what the ai would make;
         * provide additional instructions (if meaningful)
         */
        $userContent = '';

        if (\count($curriculumNames)) {
            $userContent = "Create lesson plans aligned to the curriculum areas listed below.\n\n";

            foreach ($curriculumNames as $fieldId => $val) {
                $userContent .= $val;
            }
        }

        if ('' !== $additionalContext) {
            $userContent .= sprintf(self::PROMPT_TEMPLATE_ADDITIONAL_CONTEXT, $additionalContext);
        }

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
     * @param GenericCollection $lessonPlanTemplateFields
     * @return array
     */
    private function augmentProperties(array $responseFormat, GenericCollection $lessonPlanTemplateFields): array
    {
        $struct = $responseFormat;

        /* Find some details about how to use the template fields
         */
        foreach ($lessonPlanTemplateFields as $lessonPlanTemplateField) {
            $fieldId = $lessonPlanTemplateField->getId();
            $fieldTitle = trim((string)$lessonPlanTemplateField->getTitle());
            $fieldDescription = trim((string)$lessonPlanTemplateField->getDescription());
            $fieldBody = trim((string)$lessonPlanTemplateField->getBody());

            $struct['schema']['properties'][$fieldTitle] = [
                'type' => 'string',
                'description' => $lessonPlanTemplateField->getDescription(),
            ];

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


    /* The Payload Itself
     */

    /**
     * Generates the payload for the OpenAI API request.
     *
     * @param string $systemContent
     * @param string $userContent
     * @param array $responseFormat
     * @param bool $stream
     * @return string
     * @throws \JsonException
     */
    private function generate(string $systemContent, string $userContent, array $responseFormat, bool $stream = false): string
    {
        $payload = [
            /* An ordered list of instructions for OpenAi
             */
            "messages" => [
                [
                    "role" => "system",
                    "content" => $systemContent,
                ],
                [
                    "role" => "user",
                    "content" => $userContent,
                ],
            ],
            /* Which variant of OpenAI to use
             * Each has their own way of producing results in varying ways
             * (pick one)
             */
            "model" => $this->openAiModel,
            /* A kind of OpenAI currency to use in this request
             */
            "max_tokens" => 2000,
            /* How "creative" the OpenAI response will be
             * The bigger the number, the more creative it gets
             * Goes between 0 and 2
             */
            "temperature" => self::TEMPERATURE,
            "top_p" => self::TOP_P,
            /* For futureproofing: never really used atm
             */
            "stream" => $stream,
            'format' => $responseFormat
        ];

//        new \Alaress_Probe($payload);
//        die();

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}

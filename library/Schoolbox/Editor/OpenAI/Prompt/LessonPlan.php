<?php

namespace Schoolbox\Editor\OpenAI\Prompt;

use Schoolbox\Admin\LessonPlan\Template\Field\Mapper;
use Schoolbox\Curriculum\Node;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\LessonPlan\Model;

class LessonPlan
{
    /**
     * A prompt template stolen from SA-19690 comment thread.
     */
    private const PROMPT_TEMPLATE = "
        You are an expert \"[CONTEXT]\" teacher for a class \"[FOLDER]\", proficient in creating engaging, 
        well-developed and effective lesson plans for your students. Each lesson should be engaging and
        appropriate for \"[CONTEXT]\" students.
        
        Create a range of suggestions in html that align with the following field topics; each field topic should
        use the fields_format json response format, 
            [FIELDS]
    ";


    /**
     * This ends up being a semicolon+newline separated list of curriculum node names.
     */
    private const PROMPT_TEMPLATE_CURRICULUM = '
        Each lesson should be aligned to the following curriculum outcomes:
%s.
    ';


    /**
     * Additional instructions specified by the user
     */
    private const PROMPT_TEMPLATE_ADDITIONAL_CONTEXT = '
        Additional Notes: %s
    ';


    /**
     * A structure that AI is supposed to obey, but...I don't think it cares
     */
    public const RESPONSE_FORMAT = [
        'name' => 'fields_format',
        'type' => 'json_schema',
        'strict' => true,
        'schema' => [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'string',
                ],
                'title' => [
                    'type' => 'string',
                ],
                'body' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['id', 'title', 'body']
        ],
    ];


    /**
     * @param Mapper $lessonPlanTemplateFieldMapper
     * @param Node\Mapper $curriculumNodeMapper
     */
    public function __construct(
        private \Schoolbox\Admin\LessonPlan\Template\Field\Mapper $lessonPlanTemplateFieldMapper,
        private Node\Mapper $curriculumNodeMapper
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
     * @return array
     */
    public function generateFromInstances(
        \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan,
        GenericCollection $lessonPlanTemplateFields,
        string $additionalContext = ''
    ): array {
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
        $fields = $this->compileFields($lessonPlanTemplateFields);

        /* Find some curriculum node names, if any were chosen
         */
        $curriculum = '';

        $curriculumNames = $this->compileNodeTitles($lessonPlan->getCurriculumNodes());

        if ('' !== $curriculumNames) {
            $curriculum = sprintf(
                self::PROMPT_TEMPLATE_CURRICULUM,
                $curriculumNames
            );
        }

        return $this->generate(
            $context,
            $folder,
            $fields,
            $curriculum,
            $additionalContext
        );
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
     * @return array
     */
    public function generateFromScalars(
        string $context,
        string $folder,
        array $lessonPlanTemplateFieldIds,
        array $curriculumNodeIds,
        string $additionalContext = ''
    ): array {
        /* Details about how to use each field
         */
        $lessonPlanTemplateFields = $this->compileFields($this->lessonPlanTemplateFieldMapper->findByColumn('id', $lessonPlanTemplateFieldIds));

        /* Find some curriculum node names, if any were chosen
         */
        $curriculum = '';

        $curriculumNames = $this->compileNodeTitles($this->curriculumNodeMapper->findByColumn('id', $curriculumNodeIds));

        if ('' !== $curriculumNames) {
            $curriculum = sprintf(
                self::PROMPT_TEMPLATE_CURRICULUM,
                $curriculumNames
            );
        }

        return $this->generate(
            $context,
            $folder,
            $lessonPlanTemplateFields,
            $curriculum,
            $additionalContext
        );
    }


    /**
     * Generate a prompt using Luisa Words, using the prompt attributes directly from the args.
     * These could come from a POST array or similar; generateFromInstances() lets you supply
     * actual Lesson Plan and Lesson Plan Template instances, saving you extracting this manually.
     *
     * @param string $context
     * @param string $folder
     * @param string $lessonPlanTemplateFields
     * @param string $curriculum
     * @param string $additionalContext
     * @return array
     */
    private function generate(
        string $context,
        string $folder,
        string $lessonPlanTemplateFields,
        string $curriculum,
        string $additionalContext = ''
    ): array {
        /* Conduct replacements
         */
        $systemContent = strtr(self::PROMPT_TEMPLATE, [
            '[CONTEXT]' => $context,
            '[FOLDER]' => $folder,
            '[FIELDS]' => $lessonPlanTemplateFields,
        ]);

        /* Something that shapes what the ai would make;
         * provide additional instructions (if meaningful)
         */
        $userContent = '';

        if ('' !== $curriculum) {
            $userContent .= $curriculum;
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
     * and its contents
     *
     * @param GenericCollection $lessonPlanTemplateFields
     * @return string
     */
    private function compileFields(GenericCollection $lessonPlanTemplateFields): string
    {
        $fields = [];

        /* Find some details about how to use the template fields
         */
        foreach ($lessonPlanTemplateFields as $lessonPlanTemplateField) {
            $fieldId = $lessonPlanTemplateField->getId();
            $fieldTitle = trim((string)$lessonPlanTemplateField->getTitle());
            $fieldDescription = trim((string)$lessonPlanTemplateField->getDescription());
            $fieldBody = trim((string)$lessonPlanTemplateField->getBody());

            /* Accumulate data, conditionally, if provided
             * Each field's basic synopsis: each %s represents, respectively,
             *    Lesson Plan Template Field ID
             *    Lesson Plan Template Field Title
             *    Lesson Plan Template Field Body
             *    Lesson Plan Template Field Description (which also contains extra ai instructions)
             */
            $val = '';

            $val .= "{$fieldId}\n";
            $val .= "{$fieldTitle}\n";

            if ('' !== $fieldDescription) {
                $val .= "{$fieldDescription}\n";
            }

            if ('' !== $fieldBody) {
                $val .= "{$fieldBody}\n";
            }

            $fields[] = $val;
        }

        return implode("\n\n", $fields);
    }


    /**
     * Collect something human-readable via the node identifiers
     *
     * @param GenericCollection $nodes
     * @return string
     */
    private function compileNodeTitles(GenericCollection $nodes): string
    {
        /* A list of unique curriculum node titles
         */
        $titles = [];

        foreach ($nodes as $node) {
            $titles[] = $node->getName();
        }

        return implode(";\n", array_filter(array_unique($titles)));
    }
}

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
        
        [CURRICULUM]
            
        Create a range of suggestions that align with the following field topics:
            [FIELDS]
    ";

    /**
     * Each field's basic synopsis.
     */
    private const PROMPT_TEMPLATE_FIELD = '
        Field Topic: %s.
        %s
        %s
    ';

    private const PROMPT_TEMPLATE_CURRICULUM = '
        Each lesson should be aligned to the following curriculum outcomes:
%s.
    ';


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
     * @param GenericCollection $curriculumNodes
     * @return string
     */
    public function generateFromInstances(
        \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan,
        GenericCollection $lessonPlanTemplateFields,
        GenericCollection $curriculumNodes,
    ): string
    {
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

        $curriculumNames = $this->compileNodeTitles($curriculumNodes);

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
            $curriculum
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
     * @return string
     */
    public function generateFromScalars(
        string $context,
        string $folder,
        array $lessonPlanTemplateFieldIds,
        array $curriculumNodeIds
    ): string {
        /* Details about how to use each field
         */
        $fields = $this->compileFields($this->lessonPlanTemplateFieldMapper->findByColumn('id', $lessonPlanTemplateFieldIds));

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
            $fields,
            $curriculum
        );
    }


    /**
     * Generate a prompt using Luisa Words, using the prompt attributes directly from the args.
     * These could come from a POST array or similar; generateFromInstances() lets you supply
     * actual Lesson Plan and Lesson Plan Template instances, saving you extracting this manually.
     *
     * @param string $context
     * @param string $folder
     * @param string $fields
     * @param string $curriculum
     * @return string
     */
    private function generate(
        string $context,
        string $folder,
        string $fields,
        string $curriculum,
    ): string {
        /* Conduct replacements
         */
        $replacements = [
            '[CONTEXT]' => $context,
            '[FOLDER]' => $folder,
            '[FIELDS]' => $fields,
            '[CURRICULUM]' => $curriculum
        ];

        return strtr(self::PROMPT_TEMPLATE, $replacements);
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
            $fieldTitle = trim((string)$lessonPlanTemplateField->getTitle());
            $fieldDescription = trim((string)$lessonPlanTemplateField->getDescription());
            $fieldBody = trim((string)$lessonPlanTemplateField->getBody());

            $fields[] = sprintf(
                self::PROMPT_TEMPLATE_FIELD,
                $fieldTitle,
                $fieldBody,
                $fieldDescription,
            );
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

        return implode(";\n" , array_filter(array_unique($titles)));
    }
}

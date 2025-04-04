<?php

namespace Schoolbox\Editor\OpenAI\Prompt;

use Schoolbox\Curriculum\Node;
use Schoolbox\GenericCollection;

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
            
        Create a range of suggestions that align with the following concepts:
            [FIELD_DATA].
        
        [FIELD_DESCRIPTION]
    ";

    /**
     * Each field's basic synopsis.
     */
    private const PROMPT_TEMPLATE_FIELD = '
        Field Topic: %s.
        %s.
    ';

    private const PROMPT_TEMPLATE_CURRICULUM = '
        Each lesson should be aligned to the following curriculum outcomes:
            %s.
    ';


    /**
     * @param Node\Mapper $curriculumNodeMapper
     */
    public function __construct(private Node\Mapper $curriculumNodeMapper)
    {
    }


    /**
     * Derive prompt attributes from an existing Lesson Plan and Lesson Plan Template.
     *
     * @param \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan
     * @param \Schoolbox\Admin\LessonPlan\Template\Field\Model $lessonPlanTemplateField
     * @return string
     */
    public function generateFromInstances(
        \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan,
        \Schoolbox\Admin\LessonPlan\Template\Field\Model $lessonPlanTemplateField,
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

        /* Find some details about how to use the template fields
         */
        $fieldTitle = trim((string)$lessonPlanTemplateField->getTitle());
        $fieldDescription = trim((string)$lessonPlanTemplateField->getDescription());
        $fieldBody = trim((string)$lessonPlanTemplateField->getBody());

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
            $fieldTitle,
            $fieldDescription,
            $fieldBody,
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
     * @param string $fieldTitle
     * @param string $fieldDescription
     * @param string $fieldBody
     * @param string $curriculum
     * @return string
     */
    public function generate(
        string $context,
        string $folder,
        string $fieldTitle,
        string $fieldDescription,
        string $fieldBody,
        string $curriculum,
    ): string {
        /* Conduct replacements
         */
        $replacements = [
            '[CONTEXT]' => $context,
            '[FOLDER]' => $folder,
            '[FIELD_DATA]' => sprintf(
                self::PROMPT_TEMPLATE_FIELD,
                $fieldTitle,
                $fieldBody,
            ),
            '[FIELD_DESCRIPTION]' => $fieldDescription,
            '[CURRICULUM]' => $curriculum
        ];

        return strtr(self::PROMPT_TEMPLATE, $replacements);
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

        return implode('; ', array_filter(array_unique($titles)));
    }
}

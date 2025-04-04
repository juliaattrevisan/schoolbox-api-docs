<?php

namespace Schoolbox\Editor\OpenAI\Prompt;

use DebugBar\DebugBarException;
use Schoolbox\Admin\LessonPlan\Template\Model;
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
        
        Each lesson should be aligned to the following curriculum outcomes:
            [CURRICULUM].
            
        Create a range of suggestions that align with the following concepts:
            [FIELD_DATA].
        
        [FIELD_DESCRIPTION]
    ";

    /**
     * Each field's basic synopsis.
     */
    private const PROMPT_TEMPLATE_FIELD = '
        Field Topic: %s.
        Concepts: %s.
    ';

    private const PROMPT_TEMPLATE_NODES = 'be aligned to the following: [NODES]. 
        The lesson should ';


    /**
     * @param Node\Mapper $curriculumNodeMapper
     */
    public function __construct(private Node\Mapper $curriculumNodeMapper)
    {
    }


    /**
     * Generate a prompt using Luisa Words
     *
     * @param \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan
     * @param \Schoolbox\Admin\LessonPlan\Template\Field\Model $lessonPlanTemplateField
     * @param string $title
     * @param int $duration
     * @param string $folderTitle
     * @param string $unitTitle
     * @param string $yearLevel
     * @param array $curriculumNodeIds
     * @return string
     */
    public function generate(
        \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan,
        \Schoolbox\Admin\LessonPlan\Template\Field\Model $lessonPlanTemplateField,
        string $title,
        int $duration,
        string $folderTitle,
        string $unitTitle,
        string $yearLevel,
        array $curriculumNodeIds,
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

        /* Find some details about how to use the template fields
         */
        $description = trim((string)$lessonPlanTemplateField->getDescription());

        /* Conduct replacements
         */
        $replacements = [
            '[CONTEXT]' => $context,
            '[FOLDER]' => $folder,
            '[FIELD_DATA]' => sprintf(self::PROMPT_TEMPLATE_FIELD,
                $lessonPlanTemplateField->getTitle(),
                $lessonPlanTemplateField->getBody(),
                $description ?: 'none',
            ),
            '[FIELD_DESCRIPTION]' => $lessonPlanTemplateField->getDescription(),
            '[CURRICULUM]' => $this->compileNodeTitles($lessonPlan->getCurriculumNodes()),
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

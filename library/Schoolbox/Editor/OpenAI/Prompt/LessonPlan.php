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
        You are an expert [CONTEXT] teacher for a class [FOLDER], proficient in creating engaging, well-developed 
        and effective lesson plans for your students. Each lesson plan is grouped into one or
        more fields, as follows and for each field, compose some ideas that fulfil the descriptions
        if they were provided.
        
        [FIELD_LIST] 
        
        Come up with  
        lesson plans in our unit on [UNIT TOPIC]. The lesson plans should be [DURATION] minutes each and 
        should [MODE]. The lessons should focus on: [SPECIFIC UNIT TOPICS]. 
        Each lesson should be engaging and appropriate for [YEAR LEVEL] students. Each lesson should 
        [NODES]include: learning objectives; a list of key vocabulary; a 
        lesson outline that includes direct instruction, an engaging activity, and a closure with a 
        synthesis/summary of student learning; and an assessment of learning.
    ";

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
     * @param Model $lessonPlanTemplate
     * @param string $title
     * @param int $duration
     * @param string $folderTitle
     * @param string $unitTitle
     * @param string $yearLevel
     * @param array $curriculumNodeIds
     * @param bool $compounding
     * @return string
     * @throws DebugBarException
     */
    public function generate(
        \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan,
        \Schoolbox\Admin\LessonPlan\Template\Model $lessonPlanTemplate,
        string $title,
        int $duration,
        string $folderTitle,
        string $unitTitle,
        string $yearLevel,
        array $curriculumNodeIds,
        bool $compounding = false
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

        /* Conduct replacements
         */
        $replacements = [
            '[CONTEXT]' => $context,
            '[FOLDER]' => $folder
        ];

        new \Alaress_Probe($replacements);
        die();

        //        /* Figure out what folder we were in
//         */
//        $query = "
//            SELECT
//                f1.name AS folderName,
//                f2.name AS courseName
//            FROM mv_assessment_relation mvar
//            INNER JOIN folder f1 ON f1.id = mvar.folder_id
//            INNER JOIN course_specification cs ON cs.id = mvar.course_specification_id
//            INNER JOIN folder f2 ON f2.id = cs.folder_id
//            WHERE assessment_id = :assessmentId
//        ";
//
//        $statement = $this->curriculumNodeMapper->getDatabase()->executeQuery($query, [
//            'assessmentId' => $lessonPlan->getId()
//        ]);
//
//        $folderId = null;
//
//        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
//            $data = $row['folder_id'];
//        }
//
//        new \Alaress_Probe($folderId);die();
        /* The rest of the prompt's keywords
         */
//        $replacements = [
//            '[CONTEXT]' => sprintf(
//                '%s %s %s',
//                $folderTitle,
//            '[UNIT TOPIC]' => $unitTitle,
//            '[DURATION]' => $duration,
//            '[MODE]' => $compounding ? 'build on each other' : 'stand alone',
//            '[YEAR LEVEL]' => $yearLevel,
//            '[NODES]' => $nodeTemplate,
//        ];

        /* Specifically craft a node template section, if ones were specified and meaningful
         */
        $nodes = $this->curriculumNodeMapper->findByColumn('id', $curriculumNodeIds);

        $nodeTemplate = '';

        if ($nodes->count()) {
            $replacements = [
                '[NODES]' => $this->compileNodeTitles($nodes)
            ];

            $nodeTemplate = strtr(self::PROMPT_TEMPLATE_NODES, $replacements);
        }

        /* The rest of the prompt's keywords
         */
        $replacements = [
            '[FOLDER]' => $folderTitle,
            '[UNIT TOPIC]' => $unitTitle,
            '[DURATION]' => $duration,
            '[MODE]' => $compounding ? 'build on each other' : 'stand alone',
            '[YEAR LEVEL]' => $yearLevel,
            '[NODES]' => $nodeTemplate,
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

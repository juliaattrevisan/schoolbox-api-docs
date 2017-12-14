<?php
/**
 * Nesa Report
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Help\Nesa;

use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\Mapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\RubricContext;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

class Report
{

    /**
     * Teacher Course
     * Course specification id
     */
    private $courseSpecId;

    /**
     * Indicators to examine
     * @var array
     */
    private $indicators;

    /**
     * @var Rubric\RubricMapper
     */
    private $rubricMapper;

    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * Query used to get all the Assessments in every Implementation of Course Spec Id :courseSpecId
     *
     * - The help course consists of multiple projects, which contain assessments
     * - The rubrics only apply to Due Works so we join on submission_box to exclude all other types
     * - Some assessment records contain 0000-00-00 in their created_at field (ignore these, laravel dies)
     * - Get the assessments for $folderId, for performance reasons we have to proces one folder at a time
     *
     * @var string
     */
    private $query = '
        SELECT a.id
        FROM course_specification cs
        INNER JOIN course_implementation ci ON ci.`course_specification_id` = cs.`id`
        INNER JOIN course_unit_implementation cui ON cui.`course_implementation_id` = ci.`id`
        INNER JOIN assessment_course_unit_implementation acui ON acui.course_unit_implementation_id = cui.`id`
        INNER JOIN project p ON acui.`assessment_id` = p.`assessment_id`
        INNER JOIN project_assessment pa ON pa.`project_id` = p.`id`
        INNER JOIN assessment a ON pa.`assessment_id` = a.`id`
        INNER JOIN submission_box sb ON sb.`assessment_id` = a.`id`  
        WHERE cs.id = :courseSpecId
        AND ci.folder_id = :folderId
        AND cs.deleted_at IS NULL
        AND ci.deleted_at IS NULL
        AND cui.deleted_at IS NULL
        AND p.deleted_at IS NULL
        AND a.deleted_at IS NULL
        AND a.created_at != "0000-00-00 00:00:00"
    ';

    /**
     * Nesa constructor.
     *
     * @param int                        $courseSpecId id of course spec for Teacher Course
     * @param array                      $indicators   An array of indicator text
     * @param Rubric\RubricMapper        $rubricMapper
     * @param AssessmentMapperProxy      $assessmentMapperProxy
     */
    public function __construct(
        int $courseSpecId,
        array $indicators,
        Rubric\RubricMapper $rubricMapper,
        AssessmentMapperProxy $assessmentMapperProxy
    ) {
        $this->courseSpecId = $courseSpecId;
        $this->indicators = $indicators;
        $this->rubricMapper = $rubricMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
    }


    /**
     * Find all the assessment ids that spawned from $courseSpecId
     * Use a custom query, otherwise it's just too slow
     *
     * @param $folderId
     * @return array
     */
    private function findAssessmentIds($folderId)
    {
        $assessmentMapper = Mapper::getInstance();
        $statement = $assessmentMapper->getDatabase()->prepare($this->query);
        $statement->execute([
            'folderId' => $folderId,
            'courseSpecId' => $this->courseSpecId
        ]);

        $ids = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $ids[] = $row['id'];
        }

        return $ids;
    }


    /**
     * Find all the Unit Implementations for Teacher Course
     *
     * Find all the assessments in the Teacher Course from all Unit Implementations everywhere for $folderId
     * Due to performance reasons, I have to find results per folder (otherwise too much data)
     *
     * Investigate all the assessments and their participants and harvest all the rubric selections
     *
     * @param $folderId
     * @return Collection|\Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model[]
     */
    public function generate($folderId)
    {
        /* A condensed version of the output stack that discards the capability/indicator hierarchy
         * and provides you the descriptor outputs, dumping them into a hashtable
         */
        $condensedData = [];

        /* All the assessments to investigate
         */
        $assessmentIds = $this->findAssessmentIds($folderId);

        $allAssessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds);
        $allAssessments = $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($allAssessments, null, false, false);

        foreach ($allAssessments as $assessment) {
            $assessment->setRubric($this->rubricMapper->findByDueWork($assessment));

            foreach ($assessment->getAllParticipants() as $currentParticipant) {
                $context = (new RubricContext($this->rubricMapper))
                            ->generateMarkable($assessment, $currentParticipant->getUser());

                /* Investigate the context for the user and assessment and find the chosen descriptors that
                 * exist in the set of rubric groups and criteria
                 */
                $current = $this->investigate(
                    $currentParticipant->getUser(),
                    $assessment,
                    $context['rubric'],
                    $this->indicators
                );

                /* Store first stack of condensed descriptor data for this user into a hashtable
                 * Otherwise merge into existing
                 */
                if (!isset($condensedData[$current['user']->getId()])) {
                    $condensedData[$current['user']->getId()] = [
                        'user' => [
                            'id' => $current['user']->getId(),
                            'firstname' => $current['user']->getFirstname(),
                            'lastname' => $current['user']->getLastname(),
                            'fullname' => $current['user']->getFullname()
                        ],
                        'condensed' => $current['condensed']
                    ];
                } else {
                    $existingIndicators = $condensedData[$current['user']->getId()]['condensed'];
                    $additionalIndicators = $current['condensed'];

                    $condensedData[$current['user']->getId()]['condensed'] = array_merge($existingIndicators, $additionalIndicators);
                }
            }
        }

        return $condensedData;
    }

    /**
     * Investigate a particular participant and find out what they got for a particular assessment
     * Store the ultra-basics in an array and return that for future rendering
     *
     * @param User        $user
     * @param IAssessable $assessment
     * @param array       $rubric
     * @param array       $indicators Report upon these indicators
     * @return array
     */
    private function investigate(User $user, IAssessable $assessment, array $rubric, array $indicators)
    {
        $condensed = [];

        foreach ($rubric['groups'] as $groupId => $groupData) {
            foreach ($groupData['criteria'] as $criterionId => $criterionData) {
                if (\in_array($criterionData['name'], $indicators, true)) {
                    foreach ($criterionData['descriptors'] as $levelId => $levelData) {

                        /* Only collate the ones we selected
                         * Condense this as well, for rendering
                         * The hierarchy is useful for debugging and visualisation
                         */
                        if ($levelData['selected']) {
                            if ($levelData['text'] === 'Not shown.') {

                                /* User did not demonstrate what was required
                                 */
                                $val = [
                                    'text' => $levelData['text'],
                                    'outcome' => 'N'
                                ];
                            }
                            else {

                                /* User demonstrated what was required
                                 */
                                $val = [
                                    'text' => $levelData['text'],
                                    'outcome' => 'Y'
                                ];
                            }

                            /* Indicator in the heading; descriptor text in the cell
                             */
                            $condensed[$criterionData['name']] = $val;
                        }
                    }
                }
            }
        }

        return [
            'name' => $assessment->getTitle(),
            'user' => $user,
            'assessmentId' => $assessment->getId(),
            'condensed' => $condensed,
        ];
    }
}
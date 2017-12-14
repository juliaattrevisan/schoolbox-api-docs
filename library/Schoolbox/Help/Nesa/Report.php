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
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
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
     * @var CourseImplementationMapper
     */
    private $courseImplementationMapper;

    /**
     * @var UnitImplementationMapper
     */
    private $unitImplementationMapper;

    /**
     * Query used to get all the Assessments in every Implementation of Course Spec Id :courseSpecId
     *
     * - The help course consists of multiple projects, which contain assessments
     * - The rubrics only apply to Due Works so we join on submission_box to exclude all other types
     * - Some assessment records contain 0000-00-00 in their created_at field (ignore these, laravel dies)
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
     * @param CourseImplementationMapper $courseImplementationMapper
     * @param UnitImplementationMapper   $unitImplementationMapper
     */
    public function __construct(
        int $courseSpecId,
        array $indicators,
        Rubric\RubricMapper $rubricMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        CourseImplementationMapper $courseImplementationMapper,
        UnitImplementationMapper $unitImplementationMapper
    ) {
        $this->courseSpecId = $courseSpecId;
        $this->indicators = $indicators;
        $this->rubricMapper = $rubricMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->courseImplementationMapper = $courseImplementationMapper;
        $this->unitImplementationMapper = $unitImplementationMapper;
    }


    /**
     * Find all the assessment ids that spawned from $courseSpecId
     * Use a custom query, otherwise it's just too slow
     *
     * @return array
     */
    private function findAssessmentIds()
    {
        $assessmentMapper = Mapper::getInstance();
        $statement = $assessmentMapper->getDatabase()->prepare($this->query);
        $statement->execute([
            'courseSpecId' => $this->courseSpecId
        ]);

        $ids = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $ids[] = $row['id'];
        }
return [17167];//array_slice($ids, 0, 100);
        return $ids;
    }


    /**
     * Find all the Unit Implementations for Teacher Course
     *
     * Find all the assessments in the Teacher Course from all Unit Implementations everywhere
     * Investigate all the assessments and their participants and harvest all the rubric selections
     *
     * Progressively dump vars that aren't needed to save some rams
     *
     * @return Collection|\Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model[]
     */
    public function generate()
    {
        /* A condensed version of the output stack that discards the capability/indicator hierarchy
         * and provides you the descriptor outputs, dumping them into a hashtable
         */
        $condensedData = [];

        /* All the assessments to investigate
         */
        $assessmentIds = $this->findAssessmentIds();

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
        $tempGroup = [];
        $condensed = [];

        foreach ($rubric['groups'] as $groupId => $groupData) {
            $tempCriterion = [];

            foreach ($groupData['criteria'] as $criterionId => $criterionData) {

                $tempDescriptor = [];

                if (\in_array($criterionData['name'], $indicators, true)) {
                    foreach ($criterionData['descriptors'] as $levelId => $levelData) {

                        /* Only collate the ones we selected
                         * Condense this as well, for rendering
                         * The hierarchy is useful for debugging and visualisation
                         */
                        if ($levelData['selected']) {
                            $val = [
                                'id' => $levelData['id'],
                                'text' => 'Y'
                            ];

                            $tempDescriptor[$levelData['text']] = $val;

                            /* Indicator in the heading; descriptor text in the cell
                             */
                            $condensed[$criterionData['name']] = $levelData['text'];
                        } else {
                            $val = [
                                'id' => $levelData['id'],
                                'text' => 'N'
                            ];

                            $tempDescriptor[$levelData['text']] = $val;
                        }
                    }

                    $tempCriterion[$criterionId] = [
                        'name' => $criterionData['name'],
                        'descriptors' => $tempDescriptor
                    ];
                }
            }

            $tempGroup[] = [
                'name' => $groupData['name'],
                'criteria' => $tempCriterion
            ];
        }

        $tempAssessment = [
            'name' => $assessment->getTitle(),
            'user' => $user,
            'assessmentId' => $assessment->getId(),
            'condensed' => $condensed,
            'groups' => $tempGroup,
        ];

        return $tempAssessment;
    }
}
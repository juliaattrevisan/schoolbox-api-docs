<?php
/**
 * NESA Report Data Acquisition
 *
 * Get the rubric outcomes assigned to each participation
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
use Schoolbox\ProfessionalDevelopment;
use Schoolbox\User;

class Acquisition
{
    /**
     * Course Specification Id
     * This comes from the /course/modify screen, as is an integer in the url
     */
    public const NESA_COURSE_SPEC_ID = 23;

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
        SELECT a.id, ci.folder_id
        FROM course_specification cs
        INNER JOIN course_implementation ci ON ci.`course_specification_id` = cs.`id`
        INNER JOIN course_unit_implementation cui ON cui.`course_implementation_id` = ci.`id`
        INNER JOIN assessment_course_unit_implementation acui ON acui.course_unit_implementation_id = cui.`id`
        INNER JOIN project p ON acui.`assessment_id` = p.`assessment_id`
        INNER JOIN project_assessment pa ON pa.`project_id` = p.`id`
        INNER JOIN assessment a ON pa.`assessment_id` = a.`id`
        INNER JOIN submission_box sb ON sb.`assessment_id` = a.`id`  
        WHERE cs.id = :courseSpecId
        AND FIND_IN_SET (ci.folder_id, :folderIds)
        AND cs.deleted_at IS NULL
        AND ci.deleted_at IS NULL
        AND cui.deleted_at IS NULL
        AND p.deleted_at IS NULL
        AND a.deleted_at IS NULL
        AND a.created_at != "0000-00-00 00:00:00"
    ';


    /**
     * Acquisition constructor.
     *
     * $involvement limits the number of Participants and Folders to hydrate
     *
     * @param array                          $involvement An array containing folder-user relations
     * @param array                          $indicators An array of indicator text
     * @param Rubric\RubricMapper            $rubricMapper
     * @param AssessmentMapperProxy          $assessmentMapperProxy
     */
    public function __construct(
        array $involvement,
        array $indicators,
        Rubric\RubricMapper $rubricMapper,
        AssessmentMapperProxy $assessmentMapperProxy
    ) {
        $this->involvement = $involvement;
        $this->courseSpecId = self::NESA_COURSE_SPEC_ID;
        $this->indicators = $indicators;
        $this->rubricMapper = $rubricMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
    }


    /**
     * Find all the assessment ids that spawned from $courseSpecId
     * Use a custom query, otherwise it's just too slow
     *
     * @param array $folderIds
     * @return array
     */
    private function findAssessmentIds(array $folderIds) :array
    {
        $assessmentMapper = Mapper::getInstance();
        $statement = $assessmentMapper->getDatabase()->prepare($this->query);
        $statement->execute([
            'folderIds' => implode(',', $folderIds),//$folderIds,
            'courseSpecId' => $this->courseSpecId
        ]);

        $ids = [];

        /* A tuple of (assessmentId, folderId)
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $ids[$row['id']] = $row['folder_id'];
        }

        return $ids;
    }

    /**
     * Find all the Unit Implementations for Teacher Course
     *
     * Find all the Assessments in the Teacher Course from all Unit Implementations that have people containing
     * Professional Development details
     *
     * Populate a particular subset of Participants
     *
     * @return array
     */
    public function generate() :array
    {
        /* Results of investigation into each user's assessment participation
         */
        $raw = [];

        /* All the assessments to investigate, filtered by previously-determined involvement
         */
        $assessmentIdsInFolders = $this->findAssessmentIds(array_keys($this->involvement['hierarchy']));

        /* This might have to convert to request-in-loop findById() if this data set becomes too big
         */
        $allAssessments = $this->assessmentMapperProxy->findAllByIds(array_keys($assessmentIdsInFolders));

        foreach ($allAssessments as $currentAssessment) {

            /* Load very specific participation from hashtable
             */
            $folderId = $assessmentIdsInFolders[$currentAssessment->getId()];
            $people = new Collection($this->involvement['hierarchy'][$folderId]);

            $assessment = $this->assessmentMapperProxy->loadParticularParticipantsIntoAssessment($currentAssessment, $people, false, false);

            /* Summon rubrics
             */
            $assessment->setRubric($this->rubricMapper->findByDueWork($assessment));

            foreach ($assessment->getAllParticipants() as $currentParticipant) {
                $context = (new RubricContext($this->rubricMapper))
                            ->generateMarkable($assessment, $currentParticipant->getUser());

                /* Investigate the context for the user and assessment and find the chosen descriptors that
                 * exist in the set of rubric groups and criteria
                 */
                $raw[] = $this->investigate(
                    $currentParticipant->getUser(),
                    $assessment,
                    $context['rubric'],
                    $this->indicators
                );
            }
        }

        return $raw;
    }

    /**
     * Investigate a particular participant and find out what they got for a particular assessment
     * Store the ultra-basics in an array and return that for future rendering
     *
     * @param User        $user
     * @param IAssessable $assessment
     * @param array       $rubric
     * @param array       $indicators
     * @return array
     */
    private function investigate(
        User $user,
        IAssessable $assessment,
        array $rubric,
        array $indicators) :array
    {
        $condensed = [];

        foreach ($rubric['groups'] as $groupId => $groupData) {
            foreach ($groupData['criteria'] as $criterionId => $criterionData) {
                if (\in_array($criterionData['name'], $indicators, true)) {
                    foreach ($criterionData['descriptors'] as $levelId => $levelData) {

                        /* Only collate the ones we selected; condense this for rendering
                         * array key 'outcome' used for rendering 3 states: demonstrated, not demonstrated, unknown
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
            'number' => $this->involvement['pd'][$user->getId()],
            'assessmentId' => $assessment->getId(),
            'condensed' => $condensed,
        ];
    }
}
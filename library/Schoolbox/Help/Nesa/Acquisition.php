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
use Schoolbox\Learning\Assessment\DataModel;
use Schoolbox\Learning\Assessment\DueWork\RubricContext;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Acquisition
{
    /**
     * Course Specification Id
     * This comes from the /course/modify screen, as is an integer in the url
     */
    public const NESA_COURSE_SPEC_ID = 23;

    /**
     * States to examine
     * @var array
     */
    private $states;

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
     * @var DataModel\MapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * @var array
     */
    private $involvement = [];

    /**
     * Acquisition constructor.
     *
     * $involvement limits the number of Participants and Folders to hydrate
     *
     * @param array                 $states     The Chosen States
     * @param int                   $unaccreditedOnly
     * @param array                 $indicators An array of indicator text
     * @param Rubric\RubricMapper   $rubricMapper
     * @param DataModel\MapperProxy $assessmentMapperProxy
     * @param UserMapper            $userMapper
     */
    public function __construct(
        array $states,
        int $unaccreditedOnly,
        array $indicators,
        Rubric\RubricMapper $rubricMapper,
        DataModel\MapperProxy $assessmentMapperProxy,
        UserMapper $userMapper
    ) {
        $this->states = $states;
        $this->unaccreditedOnly = $unaccreditedOnly;
        $this->courseSpecId = self::NESA_COURSE_SPEC_ID;
        $this->indicators = $indicators;
        $this->rubricMapper = $rubricMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->userMapper = $userMapper;

        /* Can't proceed without this
         */
        $this->involvement = $this->findInvolvement();
    }


    /**
     * Find all the assessment ids that spawned from $courseSpecId
     * Use a custom query, otherwise it's just too slow
     *
     * Returns a pair of hashtable; (assessmentId, folderId) tuples
     * and (user_id, pdNumber) tuples
     *
     * @return array
     */
    private function findInvolvement() :array
    {

        /* Query used to get all the Assessments in every Implementation of Course Spec Id :courseSpecId
         *
         * - The help course consists of multiple projects, which contain assessments
         * - The rubrics only apply to Due Works so we join on submission_box to exclude all other types
         * - Some assessment records contain 0000-00-00 in their created_at field (ignore these, laravel dies)
         * - Get the assessments for $folderId, for performance reasons we have to proces one folder at a time
         * - Only get folders that have our selected people in them
         */

        /* Dynamically assemble WHERE clause
         */
        $pb = new \Alaress\Database\Predicate\Builder($this->userMapper->getDatabase(), 'upd');
        $pb->equals('registration_state', $this->states);
        $pb->equals('cs.id', $this->courseSpecId);
        $pb->equals('cs.deleted_at', null);
        $pb->equals('ci.deleted_at', null);
        $pb->equals('cui.deleted_at', null);
        $pb->equals('p.deleted_at', null);
        $pb->equals('a.deleted_at', null);
        $pb->notEquals('a.created_at', '0000-00-00 00:00:00');
        $pb->equals('u.deleted_at', null);
        $pb->equals('sb.use_rubric', 1);

        /* Show only unaccredited users
         */
        if ($this->unaccreditedOnly) {
            $pb->equals('accredited_at', null);
        }

        $where = implode("\nAND ", $pb->getWhere());

        $query = "
            SELECT u.id AS user_id, upd.number, a.id AS assessment_id, ci.folder_id
            FROM user_professionaldevelopment upd
            INNER JOIN `user` u ON upd.user_id = u.`id`
            INNER JOIN userAccess ua ON u.id = ua.uid
            INNER JOIN course_implementation ci ON ua.sid = ci.`folder_id` AND ua.service = :homepageService
            INNER JOIN course_specification cs ON ci.`course_specification_id` = cs.`id`
            INNER JOIN course_unit_implementation cui ON cui.`course_implementation_id` = ci.`id`
            INNER JOIN assessment_course_unit_implementation acui ON acui.course_unit_implementation_id = cui.`id`
            INNER JOIN project p ON acui.`assessment_id` = p.`assessment_id`
            INNER JOIN project_assessment pa ON pa.`project_id` = p.`id`
            INNER JOIN assessment a ON pa.`assessment_id` = a.`id`
            INNER JOIN submission_box sb ON sb.`assessment_id` = a.`id`
            WHERE {$where}
            ORDER BY u.lastname
        ";

        $assessmentMapper = DataModel\Mapper::getInstance();
        $statement = $assessmentMapper->getDatabase()->prepare($query);
        $statement->execute(array_merge([
            'homepageService' => UserAccess::SERVICE_HOMEPAGE,
        ], $pb->getParams()));

        $involvement = [];

        /* A hashtable of (assessmentId, folderId) tuples
         * and, a hashtable of (user_id, pdNumber) tuples
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $involvement['hierarchy'][$row['assessment_id']][$row['folder_id']][] = $row['user_id'];
            $involvement['pd'][$row['user_id']] = $row['number'];
        }

        return $involvement;
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

        /* All the assessments to investigate, along with their participating user ids
         */
        $hierarchy = (array)$this->involvement['hierarchy'];

        /* This might have to convert to request-in-loop findById() if this data set becomes too big
         */
        foreach ($hierarchy as $assessmentId => $folderData) {
            try {
                $assessment = $this->assessmentMapperProxy->findById($assessmentId);

                /* Load very specific participation from hashtable
                 */
                $people = $this->userMapper->findByColumn('id', array_shift($folderData));

                /* Summon rubrics
                 */
                $assessment->setRubric($this->rubricMapper->findByDueWork($assessment));

                foreach ($people as $person) {
                    $context = (new RubricContext($this->rubricMapper))->generateMarkable($assessment, $person);

                    /* Investigate the context for the user and assessment and find the chosen descriptors that
                     * exist in the set of rubric groups and criteria.
                     */
                    $raw[] = $this->investigate(
                        $person,
                        $assessment,
                        $context['rubric'],
                        $this->indicators
                    );
                }
            }
            catch (Mapper\Exception $e) {

                /* Assessment not found; rubric not found
                 * Skip, because nothing can be done about them
                 */
                continue;
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
                                    'outcome' => false
                                ];
                            }
                            else {

                                /* User demonstrated what was required
                                 */
                                $val = [
                                    'text' => $levelData['text'],
                                    'outcome' => true
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
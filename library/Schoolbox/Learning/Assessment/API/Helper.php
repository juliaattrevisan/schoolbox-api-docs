<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\API;

use Alaress\Web\Request\Values;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Application\Exception\NotFound as NotFound;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\GenericCollection;
use Alaress\Database\Predicate\Builder;
use PDO;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Resource\Storage;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;
use Schoolbox\YearLevel\Model as YearLevelModel;

class Helper
{
    /**
     * Cache for list of year levels
     *
     * @var GenericCollection<YearLevelModel> | null
     */
    protected ?GenericCollection $yearLevels = null;

    /**
     * Cache for already looked-up folder codes
     *
     * @var array|null
     */
    protected ?array $folderCodes = null;

    /**
     * Constructor
     *
     * @param YearLevelMapper                 $mapper            Used to get the collection of year levels, and allow
     *                                                           direct DB access
     * @param Submission\SubmissionMapper     $submissionMapper  Used to fetch student submissions, and thus attached
     *                                                           files
     * @param Storage\Translator\ModelToArray $storageTranslator Used to translate student submission files into a
     *                                                           standard array format
     */
    public function __construct(
        private YearLevelMapper $mapper,
        private Submission\SubmissionMapper $submissionMapper,
        private Storage\Translator\ModelToArray $storageTranslator,
    ) {
    }

    /**
     * @param string $assessmentId
     * @return array|null
     */
    public function getAssessment(string $assessmentId): array | null
    {
        $assessments = $this->searchForAssessments(['assessmentId' => $assessmentId], 0, 0);

        if (count($assessments) === 0) {
            throw new NotFound();
        }

        return $assessments[0];
    }

    /**
     * search's assessments based on filter, and will also include feedback items for those assessments.
     * filter can include:
     * assessmentId, assessmentType, folderId, weighted, weightType (id / name)
     * dueDate (from/to), yearLevel (id / name), title
     */
    public function searchForAssessments(array $filter, int $limit, int $cursor): array
    {
        $output = [];

        [$query, $params] = $this->buildSearch($filter);

        if (0 !== $cursor) {
            $query .= "\nAND assessment.id > :cursor ";
        }

        $query .= "\nORDER BY assessment.id ";

        if (0 !== $limit) {
            $query .= "\nLIMIT :limit ";
        }

        $statement = $this->mapper->getDatabase()->prepare($query);

        if (0 !== $limit) {
            $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        }

        if (0 !== $cursor) {
            $statement->bindValue('cursor', $cursor, PDO::PARAM_INT);
        }

        foreach ($params as $label => $value) {
            $statement->bindValue($label, $value);
        }

        $statement->execute();

        /**
         * build the whole thing
         */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $yearLevel = null;

            /**
             * check if folder has yearlevels?
             */
            if (null !== $row['yearLevelId']) {
                $yearLevel = [];
                $ids = explode(',', $row['yearLevelId']);
                foreach ($ids as $id => $name) {
                    $yearLevel[] = [
                        'id'    => $this->getYearlevel($name)->getId(),
                        'name'    => $this->getYearlevel($name)->getName(),
                    ];
                }
            }

            $folderId     = (int)$row['folderId'];
            $assessmentId = (int)$row['id'];

            if (!isset($output[$assessmentId])) {
                $output[$assessmentId] = [
                    'id'               => $assessmentId,
                    'title'            => $row['title'],
                    'assessmentType'   => $row['assessmentType'],
                    'commonAssessment' => (
                        // Common assessment is only applicable to 'dueWork'.
                        ($row['assessmentType'] === 'dueWork') ? (bool)$row['commonAssessment'] : null
                    ),
                    'workType'         => (
                        [
                            'name' => $row['workTypeName'],
                            'id'   => (int)$row['workTypeId']
                        ]
                    ),
                    'folder'           => [
                        'id'        => $folderId,
                        'name'      => $row['folderName'],
                        'code'      => $this->getFolderCode($folderId),
                        'yearLevel' => $yearLevel,
                    ],
                    'subjectCode'      => $row['subjectCode'],
                    'project'          => (null === $row['project']) ? null : ['id' => (int)$row['project']],
                    'weight'           => (
                        // Weight is not applicable to 'lessonPlan' and 'task'.
                        (in_array($row['assessmentType'], ['lessonPlan', 'task'])) ? null : (float)$row['weight']
                    ),
                    'dueDate'          => $row['due_date'] ? \Alaress_Date_Format::newDatabaseDate($row['due_date'])->toRfc3339String() : null,
                    'rubric'           => $this->getRubric($assessmentId),
                    'participants'     => (
                        // Participants is not applicable to 'lessonPlan'.
                        ($row['assessmentType'] === 'lessonPlan') ? null : []
                    )
                ];
            }
        }

        // If there are no assessments, return empty string. This is a valid query and should return 200.
        if (empty($output)) {
            return [];
        }

        // Get participation (feedback + responses)
        $participationItemsByAssessment = $this->getAssessmentParticipation(array_keys($output));
        foreach ($participationItemsByAssessment as $assessmentID => $participationItems) {
            $output[$assessmentID]['participants'] = array_values($participationItems);
        }

        return array_values($output);
    }

    /**
     * returns the count of all assessments that match the filtered search
     * This is not limited by the `limit` or `cursor` parameters
     * filter can include:
     * assessmentId, assessmentType, folderId, weighted, weightType (id / name)
     * dueDate (from/to), yearLevel (id / name), title
     *
     * @param array $filter
     * @return int
     */
    public function searchForAssessmentsCount(array $filter): int
    {
        [$query, $params] = $this->buildSearch($filter);
        $statement = $this->mapper->getDatabase()->prepare($query);
        foreach ($params as $label => $value) {
            $statement->bindValue($label, $value);
        }
        $statement->execute();

        //use the magic rowCount function to grab how many assessments exist based on that search filter
        return $statement->rowCount();
    }

    /**
     * search base
     * build the search query for both count, and json response
     * will limit / build the query based on the filter
     * filter can include:
     * assessmentId, assessmentType, folderId, weighted, weightType (id / name)
     * dueDate (from/to), yearLevel (id / name), title
     *
     * @param array $filter
     * @return array [sql query, param (param key => param value)]
     */
    private function buildSearch(array $filter)
    {
        $params = [];
        //for filter Builder::getBindings
        $folderCodes = [];
        $folderIds = [];

        $select = (
            "SELECT
             assessment.id,
             assessment.title,
             assessment.weight,
             assessment.course_managed AS commonAssessment,
             lu_work_type.name AS `workTypeName`,
             lu_work_type.id AS `workTypeId`,
             assessment.due_date,
             mv_assessment_relation.folder_id AS `folderId`,
             COALESCE(
                 IF(task.id, 'task', NULL),
                 IF(assess.assessment_id, 'quiz', NULL),
                 IF(lti_assessment.assessment_id, 'LTI', NULL),
                 IF(submission_box.id, 'dueWork', NULL),
                 IF(project.id, 'project', NULL),
                 IF(lesson_plan.id, 'lessonPlan', NULL)
             ) AS `assessmentType`,
             folder.name AS `folderName`,
             (
                 SELECT course_specification.subject_code
                 FROM course_specification
                 JOIN course_implementation ON (
                    course_implementation.course_specification_id = course_specification.id AND
                    mv_assessment_relation.course_implementation_id = course_implementation.id
                 )
             ) AS `subjectCode`,
             (
                 SELECT GROUP_CONCAT(id)
                 FROM lu_year_level
                 LEFT JOIN folder_year_level ON folder_year_level.year_level_id = lu_year_level.id
                 WHERE folder_year_level.folder_id = mv_assessment_relation.folder_id
             ) AS `yearLevelId`,
             markTypeTable.hr_name AS markType,
             projectParent.assessment_id AS `project`
             "
        );

        $from = (
            "FROM mv_assessment_relation
             JOIN assessment ON mv_assessment_relation.assessment_id = assessment.id
             LEFT JOIN lu_work_type ON assessment.work_type_id = lu_work_type.id
             LEFT JOIN task ON assessment.id = task.assessment_id
             LEFT JOIN assess ON assessment.id = assess.assessment_id
             LEFT JOIN lti_assessment ON assessment.id = lti_assessment.assessment_id
             LEFT JOIN project ON assessment.id = project.assessment_id
             LEFT JOIN submission_box ON assessment.id = submission_box.assessment_id
             LEFT JOIN folder ON mv_assessment_relation.folder_id = folder.id
             LEFT JOIN submission_mark_type AS markTypeTable ON submission_box.mark_type = markTypeTable.mark_type_id
             LEFT JOIN project projectParent ON projectParent.id = mv_assessment_relation.project_assessment_id
             LEFT JOIN lesson_plan ON assessment.id = lesson_plan.assessment_id
             "
        );
        $where = (
            "WHERE assessment.deleted_at is null
            "
        );
        if (array_key_exists('assessmentId', $filter)) {
            $where .= " AND mv_assessment_relation.assessment_id = :assessmentId ";
            $params['assessmentId'] = $filter['assessmentId'];
        }
        if (array_key_exists('assessmentType', $filter)) {
            $where .= (
                " AND COALESCE(
                    IF(task.id, 'task', NULL),
                    IF(assess.assessment_id, 'quiz', NULL),
                    IF(lti_assessment.assessment_id, 'LTI', NULL),
                    IF(submission_box.id, 'dueWork', NULL),
                    IF(project.id, 'project', NULL),
                    IF(lesson_plan.id, 'lessonPlan', NULL)
                 ) = :assessmentType "
            );
            $params['assessmentType'] = $filter['assessmentType'];
        }
        if (array_key_exists('folder', $filter)) {
            //check if folder is an array
            if (is_array($filter['folder'])) {
                //iterate through items
                foreach ($filter['folder'] as $folderObj) {
                    //if id set, then add OR id
                    if (isset($folderObj['id'])) {
                        $folderIds[] = $folderObj['id'];
                    }
                    //if code set, then add OR code
                    if (isset($folderObj['code'])) {
                        $folderCodes[] = $folderObj['code'];
                    }
                }
            }
            //historic method of filtering assessments for a specific folder
            if (array_key_exists('id', $filter['folder'])) {
                $folderIds[] = $filter['folder']['id'];
            }
        }
        if (array_key_exists('weighted', $filter)) {
            $where .= " AND assessment.weight ";
            if ((new Values([$filter['weighted']]))->getBool(0) === true) {
                $where .= " != 0.00 ";
            } else {
                $where .= " = 0.00 ";
            }
        }
        if (array_key_exists('workType', $filter)) {
            if (array_key_exists('id', $filter['workType'])) {
                $where .= " AND assessment.work_type_id = :workTypeID ";
                $params['workTypeID'] = $filter['workType']['id'];
            } elseif (array_key_exists('name', $filter['workType'])) {
                $where .= " AND lu_work_type.name = :workTypeName ";
                $params['workTypeName'] = $filter['workType']['name'];
            }
        }
        if (array_key_exists('dueDate', $filter)) {
            if (array_key_exists('from', $filter['dueDate'])) {
                $where             .= " AND due_date >= FROM_UNIXTIME(:dueFrom) ";
                $fromDate = new \Alaress_Date_Format($filter['dueDate']['from'], [DATE_RFC3339, DATE_RFC3339_EXTENDED]);
                $params['dueFrom'] = $fromDate->getUnixTimeStamp();
            }
            if (array_key_exists('to', $filter['dueDate'])) {
                $where             .= " AND due_date <= FROM_UNIXTIME(:dueTo) ";
                $toDate = new \Alaress_Date_Format($filter['dueDate']['to'], [DATE_RFC3339, DATE_RFC3339_EXTENDED]);
                $params['dueTo'] = $toDate->getUnixTimeStamp();
            }
        }
        if (array_key_exists('yearLevel', $filter)) {
            if (array_key_exists('id', $filter['yearLevel'])) {
                $where .= " AND folder_year_level.year_level_id = :yearLevelId ";
                $params['yearLevelId'] = $filter['yearLevel']['id'];
                $from .= " JOIN folder_year_level on folder_year_level.folder_id = mv_assessment_relation.folder_id ";
            } elseif (array_key_exists('name', $filter['yearLevel'])) {
                $where .= " AND lu_year_level.name = :yearLevelName ";
                $params['yearLevelName'] = $filter['yearLevel']['name'];
                $from .= " JOIN folder_year_level on folder_year_level.folder_id = mv_assessment_relation.folder_id
                JOIN lu_year_level on folder_year_level.year_level_id= lu_year_level.id ";
            }
        }
        if (array_key_exists('title', $filter)) {
            $where .= " AND assessment.title like :searchParam ";
            $params['searchParam'] = '%' . $this->mapper->getDatabase()->getQuoter()->escapeForLike($filter['title']) . '%';
        }
        if (array_key_exists('project', $filter)) {
            if (array_key_exists('id', $filter['project'])) {
                $where               .= " AND projectParent.assessment_id = :projectID ";
                $params['projectID'] = $filter['project']['id'];
            }
        }
        //was a folder requested?
        if (!empty($folderIds) || !empty($folderCodes)) {
            $where .= " AND ( ";

            //were there folder id requested?
            if (!empty($folderIds)) {
                $folderIdsBindings = Builder::getBindings($folderIds, 'ids');
                $where .= " folder.id in ({$folderIdsBindings->getPlaceholder()}) ";
                $params = array_merge($params, $folderIdsBindings->getParams());
            }
            //if folder codes also requested
            if (!empty($folderIds) && !empty($folderCodes)) {
                $where .= " OR ";
            }
            //where there folder codes requested
            if (!empty($folderCodes)) {
                $folderCodesBindings = Builder::getBindings($folderCodes, 'codes');
                $where .= " folder_code.code in ({$folderCodesBindings->getPlaceholder()}) ";
                $from .= " JOIN folder_code on (mv_assessment_relation.folder_id = folder_code.folder_id and folder_code.deleted_at is NULL) ";
                $params = array_merge($params, $folderCodesBindings->getParams());
            }
            $where .= " ) ";
        }
        //subject code limit
        if (array_key_exists('subjectCode', $filter)) {
            $subjectCodesBindings = Builder::getBindings($filter['subjectCode'], 'codes');

            $from .= " JOIN course_implementation on mv_assessment_relation.course_implementation_id = course_implementation.id
                JOIN course_specification on (course_implementation.course_specification_id = course_specification.id and
                course_specification.subject_code in ({$subjectCodesBindings->getPlaceholder()}))
            ";
            $params = array_merge($params, $subjectCodesBindings->getParams());
        }

        return [
            trim($select) . "\n" . trim($from) . "\n" . trim($where), // Trim and newlines prevent bugs due to inconsistent spacing.
            $params
        ];
    }

    /**
    * Give this an array of assessment ids, and it will get all the assessment feedback items,
    * and return a nice array of them all indexed by the assessment ID.
    * @param array $assessmentIds
    * @return array
    */
    private function getAssessmentParticipation(array $assessmentIds): array
    {
        // May need to redo this completely.
        // Don't be too clever: just fetch all feedback/responses, and plug in
        // rubric where needed.
        $bindings          = Builder::getBindings($assessmentIds, 'ids');
        $participantFields = '
            `participant`.`id`             as `participant_id`,
            `participant`.`title`          as `participant_title`,
            `participant`.`firstname`      as `participant_first_name`,
            `participant`.`preferred_name` as `participant_preferred_name`,
            `participant`.`lastname`       as `participant_last_name`,
            `participant`.`synergy_id`     as `participant_external_id`
        ';
        $reviewerFields    = '
            `reviewer`.`id`             as `reviewer_id`,
            `reviewer`.`title`          as `reviewer_title`,
            `reviewer`.`firstname`      as `reviewer_first_name`,
            `reviewer`.`preferred_name` as `reviewer_preferred_name`,
            `reviewer`.`lastname`       as `reviewer_last_name`,
            `reviewer`.`synergy_id`     as `reviewer_external_id`
        ';
        $feedbackQueries = [
            <<<DUEWORK
            select
                `sb`.`assessment_id` AS `assessment_id`,
                'submission_return'  AS `feedback_table`,
                max(`sr1`.`id`)      AS `feedback_id`,
                `sr1`.`published_at` AS `completion_date`,
                `sr1`.`comment`      AS `comment`,
                `sr1`.`mark`         AS `mark`,
                `sr1`.`norm_mark`    AS `norm_mark`,
                $participantFields,
                $reviewerFields
            from (
                (`submission_return` `sr1` join `submission_box` `sb` on (`sr1`.`box_id` = `sb`.`id`))
                left join `submission_return` `sr2` on (
                    `sr1`.`box_id` = `sr2`.`box_id`
                    and `sr1`.`owner` = `sr2`.`owner`
                    and `sr1`.`review_route` = `sr2`.`review_route`
                    and `sr2`.`published_at` is not null
                    and `sr2`.`deleted_at` is null
                    and `sr1`.`published_at` < `sr2`.`published_at`
                )
                LEFT JOIN `user` `participant` on `sr1`.`owner`  = `participant`.`id`
                LEFT JOIN `user` `reviewer`    on `sr1`.`marker` = `reviewer`.`id`
            )
            where `sr1`.`published_at` is not null
              and `sr1`.`deleted_at` is null
              and `sr2`.`id` is null
              and `sr1`.`review_route` = 'instructor'
              and `sb`.`assessment_id` in ({$bindings->getPlaceholder()})
            group by `sb`.`assessment_id`, `sr1`.`owner`, `sr1`.`review_route`, `sr1`.`published_at`
            DUEWORK,
            <<<QUIZ
            select
                `ass`.`assessment_id`                   AS `assessment_id`,
                'assess_attempt'                        AS `feedback_table`,
                max(`aa1`.`attempt_id`)                 AS `feedback_id`,
                `aa1`.`reviewed_at`                     AS `completion_date`,
                `aa1`.`comment`                         AS `comment`,
                `aa1`.`mark`                            AS `mark`,
                (`aa1`.`mark` / `aa1`.`max_mark` * 100) AS `norm_mark`,
                $participantFields,
                $reviewerFields
            from (
                (`assess_attempt` `aa1` join `assess` `ass` on (`aa1`.`assess_id` = `ass`.`assess_id`))
                left join `assess_attempt` `aa2` on (
                    `aa1`.`assess_id` = `aa2`.`assess_id`
                    and `aa1`.`user_id` = `aa2`.`user_id`
                    and `aa2`.`reviewed_at` is not null
                    and `aa2`.`deleted_at` is null
                    and `aa1`.`reviewed_at` < `aa2`.`reviewed_at`
                )
                LEFT JOIN `user` `participant` on `aa1`.`user_id`     = `participant`.`id`
                LEFT JOIN `user` `reviewer`    on `aa1`.`reviewer_id` = `reviewer`.`id`
            )
            where `aa1`.`reviewed_at` is not null
              and `aa1`.`deleted_at` is null
              and `aa2`.`attempt_id` is null
              and `ass`.`assessment_id` is not null
              and `ass`.`assessment_id` in ({$bindings->getPlaceholder()})
            group by `ass`.`assessment_id`, `aa1`.`user_id`, `aa1`.`reviewed_at`
            QUIZ,
            <<<TASK
            select
                `t`.`assessment_id`     AS `assessment_id`,
                'task_completion'       AS `feedback_table`,
                max(`tc1`.`id`)         AS `feedback_id`,
                `tc1`.`completion_date` AS `completion_date`,
                null                    AS `comment`,
                null                    AS `mark`,
                null                    AS `norm_mark`,
                $participantFields,
                $reviewerFields
            from (
                (`task_completion` `tc1` join `task` `t` on (`tc1`.`task_id` = `t`.`id`))
                left join `task_completion` `tc2` on (
                    `tc1`.`task_id` = `tc2`.`task_id`
                    and `tc1`.`student_id` = `tc2`.`student_id`
                    and `tc1`.`completion_status` = `tc2`.`completion_status`
                    and `tc2`.`deleted_at` is null
                    and `tc1`.`completion_date` < `tc2`.`completion_date`
                )
                LEFT JOIN `user` `participant` on `tc1`.`student_id`      = `participant`.`id`
                LEFT JOIN `user` `reviewer`    on `tc1`.`marking_user_id` = `reviewer`.`id`
            )
            where `tc1`.`completion_date` is not null
              and `tc1`.`deleted_at` is null
              and `tc2`.`id` is null
              and `tc1`.`completion_status` = 3
              and `t`.`assessment_id` in ({$bindings->getPlaceholder()})
            group by `t`.`assessment_id`, `tc1`.`student_id`, `tc1`.`completion_date`
            TASK,
            <<<LTI
            select
                `la`.`assessment_id`                                AS `assessment_id`,
                'lti_assessment_score'                              AS `feedback_table`,
                max(`sc1`.`id`)                                     AS `feedback_id`,
                `sc1`.`created_at`                                  AS `completion_date`,
                `sc1`.`comment`                                     AS `comment`,
                `sc1`.`score_given`                                 AS `mark`,
                (`sc1`.`score_given` / `sc1`.`score_maximum` * 100) AS `norm_mark`,
                $participantFields,
                $reviewerFields
            from (
                (`lti_assessment_score` `sc1` join `lti_assessment` `la` on (`sc1`.`lti_assessment_id` = `la`.`id`))
                left join `lti_assessment_score` `sc2` on (
                    `sc1`.`lti_assessment_id` = `sc2`.`lti_assessment_id`
                    and `sc1`.`student_id` = `sc2`.`student_id`
                    and `sc2`.`deleted_at` is null
                    and `sc1`.`created_at` < `sc2`.`created_at`)
                LEFT JOIN `user` `participant` on `sc1`.`student_id`      = `participant`.`id`
                LEFT JOIN `user` `reviewer`    on `sc1`.`marking_user_id` = `reviewer`.`id`
            )
            where `sc1`.`deleted_at` is null
              and `sc1`.`is_feedback` = 1
              and `sc2`.`id` is null
              and `la`.`assessment_id` in ({$bindings->getPlaceholder()})
            group by `la`.`assessment_id`, `sc1`.`student_id`, `sc1`.`created_at`
            LTI,
            // Note LEFT JOINing user as reviewer on FALSE below is the easiest way to make the result of the query for
            // projects match the format of other assessment types.  The reviewer fields will always be NULL for projects.
            // This is because a project does not have a single reviewer.  A project is made up of sub-assessments, each
            // of which has a reviewer, which may or may not be the same user.
            <<<PROJECT
            select
                `pr`.`assessment_id` AS `assessment_id`,
                'project_result'     AS `feedback_table`,
                `prr`.`id`           AS `feedback_id`,
                `prr`.`updated_at`   AS `completion_date`,
                null                 AS `comment`,
                `prr`.`mark`         AS `mark`,
                `prr`.`norm_mark`    AS `norm_mark`,
                $participantFields,
                $reviewerFields
            from `project_result` `prr` join `project` `pr` on (`prr`.`assessment_id` = `pr`.`assessment_id`)
                LEFT JOIN `user` `participant` on `prr`.`user_id`  = `participant`.`id`
                LEFT JOIN `user` `reviewer`    on FALSE
            where
              `prr`.`deleted_at` is null
              and `pr`.`assessment_id` in ({$bindings->getPlaceholder()})
            PROJECT,
        ];

        $statement = $this->mapper->getDatabase()->query(
            '(' . implode(') union (', $feedbackQueries) . ') order by `assessment_id`, `participant_id`',
            $bindings->getParams(),
        );
        $statement->execute();
        $participationItems = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rowAssessmentId = (int)$row['assessment_id'];
            $rowParticipantId = (int)$row['participant_id'];
            if (!isset($participationItems[$rowAssessmentId])) {
                $participationItems[$rowAssessmentId] = [];
            }
            $rubric = $this->getRubricFeedback($rowAssessmentId, $row['feedback_id']);

            // Feedback fields are duplicated in the base key of the feedback
            // item, as well as in "feedback".
            $feedbackFields = [
                'mark'           => $row['mark'],
                'normalisedMark' => is_numeric($row['norm_mark']) ? (float)$row['norm_mark'] : null,
                'comment'        => $row['comment'],
                'date'           => (new \Alaress_Date_Format($row['completion_date'], \Alaress_Date_Format::DATABASE_FORMAT))->toIso8601String(),
                'instructor'     => $row['reviewer_id'] ? [
                    'id'            => (int)$row['reviewer_id'],
                    'title'         => $row['reviewer_title'],
                    'firstName'     => $row['reviewer_first_name'],
                    'preferredName' => $row['reviewer_preferred_name'],
                    'lastName'      => $row['reviewer_last_name'],
                    'externalId'    => $row['reviewer_external_id'],
                ] : null,
                'rubric'         => $rubric,
            ];

            // Add learner details
            $participationItems[$rowAssessmentId][$rowParticipantId]['learner'] ??= [
                'id'            => $rowParticipantId,
                'title'         => $row['participant_title'],
                'firstName'     => $row['participant_first_name'],
                'preferredName' => $row['participant_preferred_name'],
                'lastName'      => $row['participant_last_name'],
                'externalId'    => $row['participant_external_id'],
            ];

            // Add feedback details
            $participationItems[$rowAssessmentId][$rowParticipantId]['feedback'] = [
                'id' => (int)$row['feedback_id'],
            ] + $feedbackFields;

            // Add feedback details in a deprecated place
            $participationItems[$rowAssessmentId][$rowParticipantId] += $feedbackFields;

            // Add empty response if nothing there
            $participationItems[$rowAssessmentId][$rowParticipantId]['response'] ??= null;
        }
        $statement->close();


        // Also fetch most recent response for this item
        // Format:
        // "id": 10,
        // "date": "2022-01-30T21:00:00.000Z",
        // "mark": "B",
        // "normalisedMark": "30",
        // "rubric": {...},
        // "files": [
        //     {`standard files object`}
        // ]
        $submitterFields    = '
            `submitter`.`id`             as `submitter_id`,
            `submitter`.`title`          as `submitter_title`,
            `submitter`.`firstname`      as `submitter_first_name`,
            `submitter`.`preferred_name` as `submitter_preferred_name`,
            `submitter`.`lastname`       as `submitter_last_name`,
            `submitter`.`synergy_id`     as `submitter_external_id`
        ';
        $responseQueries = [
            <<<DUEWORK
            select
                `sb`.`assessment_id` AS `assessment_id`,
                'student_submission' AS `response_table`,
                max(ss1.`id`)        AS `response_id`,
                ss1.`submitted_at`   AS `completion_date`,
                null                 AS `comment`,
                null                 AS `mark`,
                null                 AS `norm_mark`,
                $participantFields,
                $submitterFields
            from (
                (`student_submission` ss1 join `submission_box` `sb` on (ss1.`box_id` = `sb`.`id`))
                left join `student_submission` ss2 on (
                    ss1.`box_id` = ss2.`box_id`
                    and ss1.`owner` = ss2.`owner`
                    and ss2.`submitted_at` is not null
                    and ss2.`deleted_at` is null
                    and ss1.`submitted_at` < ss2.`submitted_at`
                )
                LEFT JOIN `user` `participant` on ss1.`owner`        = `participant`.`id`
                LEFT JOIN `user` `submitter`   on ss1.`submitted_by` = `submitter`.`id`
            )
            where ss1.`submitted_at` is not null
              and ss1.`deleted_at` is null
              and ss2.`id` is null
              and `sb`.`assessment_id` in ({$bindings->getPlaceholder()})
            group by `sb`.`assessment_id`, ss1.`owner`, ss1.`submitted_at`
            DUEWORK,
            <<<QUIZ
            select
                `ass`.`assessment_id`                   AS `assessment_id`,
                'assess_attempt'                        AS `response_table`,
                max(`aa1`.`attempt_id`)                 AS `response_id`,
                `aa1`.`finish`                          AS `completion_date`,
                `aa1`.`comment`                         AS `comment`,
                `aa1`.`mark`                            AS `mark`,
                (`aa1`.`mark` / `aa1`.`max_mark` * 100) AS `norm_mark`,
                $participantFields,
                $submitterFields
            from (
                (`assess_attempt` `aa1` join `assess` `ass` on (`aa1`.`assess_id` = `ass`.`assess_id`))
                left join `assess_attempt` `aa2` on (
                    `aa1`.`assess_id` = `aa2`.`assess_id`
                    and `aa1`.`user_id` = `aa2`.`user_id`
                    and `aa2`.`finish` is not null
                    and `aa2`.`deleted_at` is null
                    and `aa1`.`finish` < `aa2`.`finish`
                )
                -- Submitting user is always the same as the participant:
                -- teachers can't submit on behalf of a student
                LEFT JOIN `user` `participant` on `aa1`.`user_id` = `participant`.`id`
                LEFT JOIN `user` `submitter`   on `aa1`.`user_id` = `submitter`.`id`
            )
            where `aa1`.`finish` is not null
              and `aa1`.`deleted_at` is null
              and `aa2`.`attempt_id` is null
              and `ass`.`assessment_id` is not null
              and `ass`.`assessment_id` in ({$bindings->getPlaceholder()})
            group by `ass`.`assessment_id`, `aa1`.`user_id`, `aa1`.`reviewed_at`
            QUIZ,
            <<<TASK
            select
                `t`.`assessment_id`     AS `assessment_id`,
                'task_completion'       AS `response_table`,
                max(`tc1`.`id`)         AS `response_id`,
                `tc1`.`completion_date` AS `completion_date`,
                null                    AS `comment`,
                null                    AS `mark`,
                null                    AS `norm_mark`,
                $participantFields,
                $submitterFields
            from (
                (`task_completion` `tc1` join `task` `t` on (`tc1`.`task_id` = `t`.`id`))
                left join `task_completion` `tc2` on (
                    `tc1`.`task_id` = `tc2`.`task_id`
                    and `tc1`.`student_id` = `tc2`.`student_id`
                    and `tc1`.`completion_status` = `tc2`.`completion_status`
                    and `tc2`.`deleted_at` is null
                    and `tc1`.`completion_date` < `tc2`.`completion_date`
                )
                LEFT JOIN `user` `participant` on `tc1`.`student_id`      = `participant`.`id`
                LEFT JOIN `user` `submitter`   on `tc1`.`marking_user_id` = `submitter`.`id`
            )
            where `tc1`.`completion_date` is not null
              and `tc1`.`deleted_at` is null
              and `tc2`.`id` is null
              and `tc1`.`completion_status` = 2
              and `t`.`assessment_id` in ({$bindings->getPlaceholder()})
            group by `t`.`assessment_id`, `tc1`.`student_id`, `tc1`.`completion_date`
            TASK,
            <<<LTI
            select
                `la`.`assessment_id`                                AS `assessment_id`,
                'lti_assessment_score'                              AS `response_table`,
                max(`sc1`.`id`)                                     AS `response_id`,
                `sc1`.`created_at`                                  AS `completion_date`,
                `sc1`.`comment`                                     AS `comment`,
                `sc1`.`score_given`                                 AS `mark`,
                (`sc1`.`score_given` / `sc1`.`score_maximum` * 100) AS `norm_mark`,
                $participantFields,
                $submitterFields
            from (
                (`lti_assessment_score` `sc1` join `lti_assessment` `la` on (`sc1`.`lti_assessment_id` = `la`.`id`))
                left join `lti_assessment_score` `sc2` on (
                    `sc1`.`lti_assessment_id` = `sc2`.`lti_assessment_id`
                    and `sc1`.`student_id` = `sc2`.`student_id`
                    and `sc2`.`deleted_at` is null
                    and `sc1`.`created_at` < `sc2`.`created_at`)
                LEFT JOIN `user` `participant` on `sc1`.`student_id`      = `participant`.`id`
                LEFT JOIN `user` `submitter`   on `sc1`.`marking_user_id` = `submitter`.`id`
            )
            where `sc1`.`deleted_at` is null
              and `sc1`.`is_feedback` = 1
              and `sc2`.`id` is null
              and `la`.`assessment_id` in ({$bindings->getPlaceholder()})
            group by `la`.`assessment_id`, `sc1`.`student_id`, `sc1`.`created_at`
            LTI,
        ];

        $responseStatement = $this->mapper->getDatabase()->query(
            '(' . implode(') union (', $responseQueries) . ') order by `assessment_id`, `participant_id`',
            $bindings->getParams(),
        );
        $responseStatement->execute();
        $studentSubmissionIds = [];
        $responseRows = $responseStatement->fetchAll();
        foreach ($responseRows as $row) {
            // Add learner details
            $rowAssessmentId = (int)$row['assessment_id'];
            $rowParticipantId = (int)$row['participant_id'];
            $participationItems[$rowAssessmentId][$rowParticipantId]['learner'] ??= [
                'id'            => $rowParticipantId,
                'title'         => $row['participant_title'],
                'firstName'     => $row['participant_first_name'],
                'preferredName' => $row['participant_preferred_name'],
                'lastName'      => $row['participant_last_name'],
                'externalId'    => $row['participant_external_id'],
            ];

            // Add response details
            $participationItems[$rowAssessmentId][$rowParticipantId]['response'] = [
                'id'        => $row['response_id'],
                'date'      => (new \Alaress_Date_Format($row['completion_date'], \Alaress_Date_Format::DATABASE_FORMAT))->toIso8601String(),
                'submitter' => [
                    'id'            => $row['submitter_id'],
                    'title'         => $row['submitter_title'],
                    'firstName'     => $row['submitter_first_name'],
                    'preferredName' => $row['submitter_preferred_name'],
                    'lastName'      => $row['submitter_last_name'],
                    'externalId'    => $row['submitter_external_id'],
                ],
                'files'     => null,
            ];

            // Track files (due work responses only):
            // these will be fetched in a later query
            if ($row['response_table'] === 'student_submission') {
                $participationItems[$rowAssessmentId][$rowParticipantId]['response']['files'] = [];
                $studentSubmissionIds[] = $row['response_id'];
            }

            // Add empty feedback if nothing there
            $participationItems[$rowAssessmentId][$rowParticipantId]['feedback'] ??= null;
        }

        // Load submissions, and thus their files
        if (!empty($studentSubmissionIds)) {
            /** @var iterable<Submission\Submission> $studentSubmissions */
            $studentSubmissions = $this->submissionMapper->findByColumn('id', $studentSubmissionIds);
            foreach ($studentSubmissions as $studentSubmission) {
                $assessmentId = $studentSubmission->getSubmissionBox()->getAssessmentModel()->getId();
                $studentId    = $studentSubmission->getOwner()->getId();
                $participationItems[$assessmentId][$studentId]['response']['files'] = $studentSubmission
                    ->getFiles()
                    ->map($this->storageTranslator)
                    ->reindex();
            }
        }


        return $participationItems;
    }

    /**
     * get nice yearlevel objects
     */
    public function getYearlevel(int $yearLevelId): YearLevelModel
    {
        if (null === $this->yearLevels) {
            $this->yearLevels = $this->mapper->findAll()->indexBy('id');
        }
        return $this->yearLevels[$yearLevelId];
    }

    /**
     * get nice folderCode objects
     * if the folder has no code, it will return null
     * if the folder has a single code, it will return a string of the code
     * if a folder has multiple codes, it will create an array with each code contained
     */
    public function getFolderCode(int $folderId)
    {
        if (null === $this->folderCodes) {
            $statement = $this->mapper->getDatabase()->prepare("
            SELECT folder_id, code
            FROM folder_code
            WHERE deleted_at is NULL
            ");
            $statement->execute();
            $this->folderCodes = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                /**
                 * lets go backwards for the checks, if the entry has not been saved, save it now.
                 */
                if (!isset($this->folderCodes[$row['folder_id']])) {
                    $this->folderCodes[$row['folder_id']] = $row['code'];
                    continue;
                }
                /**
                 * the folder ID exists, there check if its an array and append the current folder code.
                 */
                if (is_array($this->folderCodes[$row['folder_id']])) {
                    $this->folderCodes[$row['folder_id']][] = $row['code'];
                } else {
                    /**
                     * entry is not an array, lets change it to one now...
                     * and whilst we are at is, lets append the new code.
                     */
                    /**
                     * let's be completly specific about the movement of data.
                     * I don't know the order values are assigned, or if that will change in the future
                     *
                     * original idea was to:
                     * `$this->folderCodes[$row['folder_id']] = [$this->folderCodes[$row['folder_id']], $row['code']];`
                     */
                    $oldCode = $this->folderCodes[$row['folder_id']];
                    $this->folderCodes[$row['folder_id']] = [$oldCode, $row['code']];
                }
            }
        }
        return $this->folderCodes[$folderId] ?? null;
    }

    /**
     * get full rubric template
     * @param string $assessmentId
     * @return array|null
     */
    public function getRubric(string $assessmentId): ?array
    {
        $statement = $this->mapper->getDatabase()->prepare("
        SELECT
            rd.id as `descriptor_id`,
            rd.max_value as `descriptor_max_value`,
            rd.description as `descriptor_descriptor`,
            rl.sequence as `descriptor_sequence`,
            t.*
        FROM v_rubric t
                 INNER JOIN submission_box sb on t.assessment_id = sb.assessment_id
                 INNER JOIN rubric_descriptor rd ON (t.indicator_id = rd.rubric_indicator_id and rd.deleted_at is null)
                 INNER JOIN rubric_level rl on (rd.level_id = rl.id and sb.id = rl.box_id and rl.deleted_at is null)
        WHERE t.assessment_id =  :assessmentID
        ");
        $statement->execute(['assessmentID' => $assessmentId]);
        $output = [];
        //if assessment has no rubric, return null
        if (0 === $statement->rowCount()) {
            return null;
        }
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            //build the capability if it does not already exist
            if (!isset($output['capabilities'][$row['capability_id']])) {
                $output['capabilities'][$row['capability_id']] = [
                    'id'            => (int)$row['capability_id'],
                    'name'          => $row['capability_name'],
                    'maxValue'     => (float)$row['capability_max_value'],
                    'indicators'    => []
                ];
            }
            //capability should already exist, so lets build the indicator for that capability
            if (!isset($output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']])) {
                $output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']] = [
                    'id'            => (int)$row['indicator_id'],
                    'name'          => $row['indicator_name'],
                    'maxValue'      => (float)$row['indicator_max_value'],
                    'descriptors'   => []
                ];
            }
            //indicator should already exist, so lets build the descriptor for that indicator for that capability
            if (!isset($output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']]['descriptors'][$row['descriptor_id']])) {
                $output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']]['descriptors'][$row['descriptor_id']] = [
                    'id'            => (int)$row['descriptor_id'],
                    'description'   => $row['descriptor_descriptor'],
                    'maxValue'      => (float)$row['descriptor_max_value'],
                    'sequence'      => (int)$row['descriptor_sequence']
                ];
            }
        }
        //clean the data, remove the numeric array keys
        foreach ($output['capabilities'] as $capKey => $capArr) {
            foreach ($capArr['indicators'] as $indKey => $indArr) {
                $output['capabilities'][$capKey]['indicators'][$indKey]['descriptors'] = array_values($indArr['descriptors']);
            }
            //grab the original array indicators values which above has changed, not the old ones from the foreach loop that have numeric keys
            $output['capabilities'][$capKey]['indicators'] = array_values($output['capabilities'][$capKey]['indicators']);
        }
        $output['capabilities'] = array_values($output['capabilities']);
        return $output;
    }

    /**
     * get feedback rubric
     *
     * @param string $assessmentId
     * @param int    $feedbackID
     * @return null|array
     */
    public function getRubricFeedback(string $assessmentId, int $feedbackID): ?array
    {
        $statement = $this->mapper->getDatabase()->prepare("
        select `sb`.`assessment_id`                                  AS `assessment_id`,
           `vr`.`capability_mark_type_id`                        AS `capability_mark_type_id`,
           `vr`.`capability_grade_group_id`                      AS `capability_grade_group_id`,
           `sr`.`owner`                                          AS `student_id`,
           `sr`.`marker`                                         AS `marker_id`,
           `sr`.`id`                                             AS `feedback_id`,
           `sr`.`published_at`                                   AS `feedback_date`,
           `sr`.`review_route`                                   AS `feedback_type`,
           `vr`.`capability_id`                                  AS `capability_id`,
           `vr`.`capability_name`                                AS `capability_name`,
           `vr`.`capability_max_value`                           AS `capability_max_value`,
           if(`rcm`.`valid`, `rcm`.`value`, NULL)                AS `capability_mark`,
           if(`rcm`.`valid`, `rcm`.`human_readable_value`, NULL) AS `capability_human_readable_mark`,
           `vr`.`indicator_id`                                   AS `indicator_id`,
           `vr`.`indicator_name`                                 AS `indicator_name`,
           `vr`.`indicator_max_value`                            AS `indicator_max_value`,
           if(`rim`.`valid`, `rim`.`value`, NULL)                AS `indicator_mark`,
           `rim`.`descriptor_id`                                 AS `descriptor_id`,
           `rim`.`descriptor_name`                               AS `descriptor_name`,
           `rd`.`max_value`                                      AS `descriptor_max_value`,
           `rd`.`description`                                    AS `descriptor_descriptor`,
           `rl`.`sequence`                                       AS `descriptor_sequence`
        from ((((`v_rubric` `vr`
            join `submission_box` `sb`on (`vr`.`assessment_id` = `sb`.`assessment_id`))
            join `submission_return` `sr` on (`sb`.`id` = `sr`.`box_id`))
            join `rubric_capability_mark` `rcm` on (`rcm`.`return_id` = `sr`.`id` and `rcm`.`rubric_capability_id` =
                                                     `vr`.`capability_id`))
            left join `v_rubric_indicator_mark_extend` `rim` on (`rim`.`rubric_indicator_id` = `vr`.`indicator_id` and `rcm`.`id` = `rim`.`rubric_capability_mark_id`))
            LEFT JOIN `rubric_descriptor` `rd` on (`rim`.`descriptor_id` = `rd`.`id` and `rd`.`deleted_at` is null)
            LEFT JOIN `rubric_level` `rl` on (`rd`.`level_id` = `rl`.`id` and `sb`.`id` = `rl`.`box_id` and `rl`.`deleted_at` is null)
        where `sr`.`deleted_at` is null
            and `sr`.`published_at` is not null
            and (`rcm`.`valid` is true or `rim`.`valid` is true)
            and
                `sb`.`assessment_id` = :assessmentID
            AND
                `sr`.`review_route` = 'instructor'
            AND
                `sr`.`id` = :feedbackID
        order by `sb`.`assessment_id`, `sr`.`owner`, `sr`.`published_at`, `vr`.`capability_sequence`, `vr`.`capability_id`,
            `vr`.`indicator_sequence`, `vr`.`indicator_id`
        ");
        $statement->execute(
            [
                'assessmentID'  => $assessmentId,
                'feedbackID'    => $feedbackID,
            ]
        );
        $output = [];
        //if assessment has no rubric, return null
        if (0 === $statement->rowCount()) {
            return null;
        }
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            //build the capability if it does not already exist
            if (!isset($output['capabilities'][$row['capability_id']])) {
                $output['capabilities'][$row['capability_id']] = [
                    'id'                    => (int)$row['capability_id'],
                    'name'                  => $row['capability_name'],
                    'value'                 => (null === $row['capability_mark']) ? null : (float)$row['capability_mark'],
                    'maxValue'              => (float)$row['capability_max_value'],
                    'mark'                  => $row['capability_human_readable_mark'],
                    'indicators'            => []
                ];
            }
            //capability should already exist, so lets build the indicator for that capability
            if (!isset($output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']])) {
                $output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']] = [
                    'id'            => (int)$row['indicator_id'],
                    'name'          => $row['indicator_name'],
                    'maxValue'      => (float)$row['indicator_max_value'],
                    'value'         => (float)$row['indicator_mark'],
                    'descriptor'    => null,
                    ];
                //if descriptor is not null, then enter it
                if (null != $row['descriptor_name']) {
                    $output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']]['descriptor'] = [
                        'id'            => (int)$row['descriptor_id'],
                        'description'   => $row['descriptor_name'],
                        'maxValue'      => (float)$row['descriptor_max_value'],
                        'sequence'      => (int)$row['descriptor_sequence']
                    ];
                }
            }
        }
        //clean the data, remove the numeric array keys
        foreach ($output['capabilities'] as $capKey => $capArr) {
            //grab the original array indicators values which above has changed, not the old ones from the foreach loop that have numeric keys
            $output['capabilities'][$capKey]['indicators'] = array_values($output['capabilities'][$capKey]['indicators']);
        }
        $output['capabilities'] = array_values($output['capabilities']);
        return $output;
    }

    /**
     * @param array $filter
     * @return bool
     */
    public function validateFilter(array $filter): bool
    {
        $stringFields  = [
            'title'       => true,
            'folder'      => ['code'],
            'subjectCode' => [],
            'workType'    => ['name'],
            'yearLevel'   => ['name'],
        ];
        $numericFields = [
            'assessmentId' => true,
            'folder'       => ['id'],
            'workType'     => ['id'],
            'yearLevel'    => ['id'],
            'project'      => ['id'],
        ];
        $enumFields    = [
            'assessmentType' => [
                AssessmentType::TYPE_TASK,
                AssessmentType::TYPE_QUIZ,
                AssessmentType::TYPE_LTI_ASSESSMENT,
                AssessmentType::TYPE_DUE_WORK,
                AssessmentType::TYPE_PROJECT,
                AssessmentType::TYPE_LESSON_PLAN
            ],
        ];
        $dateFields    = [
            'dueDate' => ['from', 'to'],
        ];
        $booleanFields = [
            'weighted' => true,
        ];
        foreach ($filter as $filterName => $filterValue) {
            $found = false;
            // string
            if (isset($stringFields[$filterName])) {
                if (is_array($stringFields[$filterName])) {
                    if (!is_array($filterValue)) {
                        throw new SchoolboxException("Filter $filterName must be an array.");
                    }
                    foreach ($stringFields[$filterName] as $fieldAttribute) {
                        if (isset($filterValue[$fieldAttribute]) && !is_string($filterValue[$fieldAttribute])) {
                            throw new SchoolboxException("Filter $filterName - $filterValue[$fieldAttribute] must be a string.");
                        }
                    }
                } elseif (!is_string($filterValue)) {
                    throw new SchoolboxException("Filter $filterName - $filterName must be a string.");
                }
                $found = true;
            }
            // numeric
            if (isset($numericFields[$filterName])) {
                if (is_array($numericFields[$filterName])) {
                    foreach ($numericFields[$filterName] as $fieldAttribute) {
                        if (isset($filterValue[$fieldAttribute]) && !is_numeric($filterValue[$fieldAttribute])) {
                            throw new SchoolboxException("Filter $filterName - $filterValue[$fieldAttribute] must be a number.");
                        }
                    }
                } elseif (!is_numeric($filterValue)) {
                    throw new SchoolboxException("Filter $filterName - $filterValue must be a number.");
                }
                $found = true;
            }
            // bool
            if (isset($booleanFields[$filterName])) {
                if (!is_bool((new Values([$filterValue]))->getBool(0))) {
                    throw new SchoolboxException("Filter $filterName - $filterValue must be a boolean.");
                }
                $found = true;
            }
            // date
            if (isset($dateFields[$filterName])) {
                if (!is_array($filterValue)) {
                    throw new SchoolboxException("Filter $filterName must be an array.");
                }
                foreach ($dateFields[$filterName] as $fieldAttribute) {
                    if (!isset($filterValue[$fieldAttribute])) {
                        continue;
                    }
                    if (!is_string($filterValue[$fieldAttribute])) {
                        throw new SchoolboxException("Filter $filterName - $fieldAttribute must be a string.");
                    }
                    try {
                        $date = new \Alaress_Date_Format($filterValue[$fieldAttribute], [
                            DATE_RFC3339,
                            DATE_RFC3339_EXTENDED,
                        ]);
                    } catch (\Exception $e) {
                        throw new SchoolboxException("Failed to parse date from $filterName - {$filterValue[$fieldAttribute]}.");
                    }
                }
                $found = true;
            }
            // enum
            if (isset($enumFields[$filterName])) {
                if (!in_array($filterValue, $enumFields[$filterName])) {
                    throw new SchoolboxException("$filterValue must be one of " . implode(',', $enumFields[$filterName]));
                }
                $found = true;
            }
            if (!$found) {
                throw new SchoolboxException("Unknown filter $filterName.");
            }
        }
        return true;
    }
}

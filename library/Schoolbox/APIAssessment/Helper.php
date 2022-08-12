<?php

namespace Schoolbox\APIAssessment;

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\GenericCollection;
use Alaress\Database\Predicate\Builder;
use PDO;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;
use Schoolbox\YearLevel\Model as YearLevelModel;

class Helper
{
    /**
     * @var GenericCollection | YearLevelModel[] | null
     */
    protected ?GenericCollection $yearLevels = null;

    protected YearLevelMapper $mapper;

    protected ?array $folderCodes = null;

    /**
     * YearLevel Mapper is being used to get the collection of year levels,
     * and provide the database interface for building specific queries
     * @param YearLevelMapper $mapper
     */
    public function __construct(YearLevelMapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * @param string $assessmentId
     * @return array
     */
    public function getAssessment(string $assessmentId): array
    {
        return $this->searchForAssessments(['assessmentId' => $assessmentId], 0, 0)[0];
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
            $query .= " AND assessment.id > :cursor ";
        }
        $query .= "ORDER BY assessment.id";
        if (0 !== $limit) {
            $query .= " LIMIT :limit";
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
            $weighted = false;
            if ('0.00' !== $row['weight']) {
                $weighted = true;
            }
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
            $folder = [
                'id' => $row['folderId'],
                'name' => $row['folderName'],
                'code' => $this->getFolderCode($row['folderId']),
                'yearLevel' => $yearLevel,
            ];
            $project = null;
            if (null !== $row['project']) {
                $project = [
                    'id' => $row['project'],
                ];
            }
            //get the rubric for the assessment
            $rubric = $this->getRubric($row['id']);
            if (!isset($output[$row['id']])) {
                $output[$row['id']] = [
                    'id'               => $row['id'],
                    'title'            => $row['title'],
                    'assessmentType'   => $row['assessmentType'],
                    'commonAssessment' => (bool)$row['commonAssessment'],
                    'workType'         => [
                        'name' => $row['workTypeName'],
                        'id'   => $row['workTypeId']
                    ],
                    'folder'           => $folder,
                    'project'          => $project,
                    'weighted'         => $weighted,
                    'dueDate'          => \Alaress_Date_Format::newDatabaseDate($row['due_date'])->toRfc3339String(),
                    'rubric'           => $rubric,
                    'participants'     => null,
                ];
            }
        }
        //if there are no assessments, just return an error, saying the assessment does not exist?
        if (empty($output)) {
            throw new SchoolboxException("No assessments found");
        }
        //get feedback items
        $feedbacks = $this->getAssessmentFeedback(array_keys($output));
        foreach ($feedbacks as $assessmentID => $feedback) {
            $output[$assessmentID]['participants'] = array_values($feedback);
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

        $select = "SELECT
    assessment.id,
    assessment.title,
    assessment.weight,
    assessment.course_managed as commonAssessment,
    lu_work_type.name as `workTypeName`,
    lu_work_type.id as `workTypeId`,
    assessment.due_date,
    v_assessment_relation.folder_id as `folderId`,
    COALESCE(
            if(task.id, 'Task', null),
            if(assess.assessment_id, 'Quiz', null),
            if(lti_assessment.assessment_id, 'LTI', null),
            if(submission_box.id, 'DueWork', null),
            if(project.id, 'Project', null)
        ) as `assessmentType`,
    folder.name as `folderName`,
    (SELECT group_concat(id) from lu_year_level
                                      LEFT JOIN folder_year_level on folder_year_level.year_level_id= lu_year_level.id
     where folder_year_level.folder_id = v_assessment_relation.folder_id) as `yearLevelId`,
    markTypeTable.hr_name AS markType,
        projectParent.assessment_id as `project`
    ";

        $from = "FROM v_assessment_relation
         JOIN assessment on v_assessment_relation.assessment_id = assessment.id
         LEFT JOIN lu_work_type on assessment.work_type_id = lu_work_type.id
         LEFT JOIN task on assessment.id = task.assessment_id
         LEFT JOIN assess on assessment.id = assess.assessment_id
         LEFT JOIN lti_assessment on assessment.id = lti_assessment.assessment_id
         LEFT JOIN project on assessment.id = project.assessment_id
         LEFT JOIN submission_box ON assessment.id = submission_box.assessment_id
         LEFT JOIN folder on v_assessment_relation.folder_id = folder.id

         LEFT JOIN submission_mark_type as markTypeTable ON submission_box.mark_type = markTypeTable.mark_type_id

        LEFT JOIN project projectParent on projectParent.id = v_assessment_relation.project_assessment_id
";
        $where = "
WHERE
    assessment.deleted_at is null
";
        if (array_key_exists('assessmentId', $filter)) {
            $where .= " AND v_assessment_relation.assessment_id = :assessmentId ";
            $params['assessmentId'] = $filter['assessmentId'];
        }
        if (array_key_exists('assessmentType', $filter)) {
            $where .= " AND COALESCE(
            if(task.id, 'Task', null),
            if(assess.assessment_id, 'Quiz', null),
            if(lti_assessment.assessment_id, 'LTI', null),
            if(submission_box.id, 'DueWork', null),
            if(project.id, 'Project', null)
        ) = :assessmentType ";
            $params['assessmentType'] = $filter['assessmentType'];
        }
        if (array_key_exists('folder', $filter)) {
            if (array_key_exists('id', $filter['folder'])) {
                $where              .= " AND v_assessment_relation.folder_id = :folderId ";
                $params['folderId'] = $filter['folder']['id'];
            }
        }
        if (array_key_exists('weighted', $filter)) {
            $where .= " AND assessment.weight";
            if ((bool)$filter['weighted'] === true) {
                $where .= " != 0.00 ";
            } else {
                $where .= " = 0.00 ";
            }
        }
        if (array_key_exists('workType', $filter)) {
            if (array_key_exists('id', $filter['workType'])) {
                if (in_array($filter['workType']['id'], [null, 'null', ''])) {
                    $where .= " AND assessment.work_type_id is null ";
                } else {
                    $where .= " AND assessment.work_type_id = :workTypeID ";
                    $params['workTypeID'] = $filter['workType']['id'];
                }
            } elseif (array_key_exists('name', $filter['workType'])) {
                $where .= " AND lu_work_type.name = :workTypeName ";
                $params['workTypeName'] = $filter['workType']['name'];
            }
        }
        if (array_key_exists('dueDate', $filter)) {
            if (array_key_exists('from', $filter['dueDate'])) {
                $where             .= " AND due_date >= FROM_UNIXTIME(:dueFrom) ";
                $params['dueFrom'] = \DateTime::createFromFormat(DATE_RFC3339, $filter['dueDate']['from'])->getTimestamp();
            }
            if (array_key_exists('to', $filter['dueDate'])) {
                $where             .= " AND due_date <= FROM_UNIXTIME(:dueTo) ";
                $params['dueTo'] = \DateTime::createFromFormat(DATE_RFC3339, $filter['dueDate']['to'])->getTimestamp();
            }
        }
        if (array_key_exists('yearLevel', $filter)) {
            if (array_key_exists('id', $filter['yearLevel'])) {
                $where .= " AND folder_year_level.year_level_id = :yearLevelId ";
                $params['yearLevelId'] = $filter['yearLevel']['id'];
                $from .= "JOIN folder_year_level on folder_year_level.folder_id = v_assessment_relation.folder_id";
            } elseif (array_key_exists('name', $filter['yearLevel'])) {
                $where .= " AND lu_year_level.name = :yearLevelName ";
                $params['yearLevelName'] = $filter['yearLevel']['name'];
                $from .= "JOIN folder_year_level on folder_year_level.folder_id = v_assessment_relation.folder_id
                JOIN lu_year_level on folder_year_level.year_level_id= lu_year_level.id";
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
        return [
            $select . $from . $where,
            $params
        ];
    }

    /**
    * give this an array of assessment id's and it will get all the assessment feedback items,
    * and return a nice array of them all indexed by the assessment ID
    * @param array $assessmentIds
    * @return array
    */
    private function getAssessmentFeedback(array $assessmentIds): array
    {
        $bindings = Builder::getBindings($assessmentIds, 'ids');
        $statement = $this->mapper->getDatabase()->query("
        (select `sb`.`assessment_id` AS `assessment_id`,
        'submission_return'  AS `feedback_table`,
        max(`sr1`.`id`)      AS `feedback_id`,
        `sr1`.`owner`        AS `student_id`,
        `sr1`.`created_at`   AS `completionDate`,
        `sr1`.`comment`      AS `comment`,
        `sr1`.`mark`         AS `mark`,
        `sr1`.`norm_mark`    AS `norm_mark`,
        `student`.`title` as `studentTitle`,
        `student`.`firstname` as `studentFirstname`,
        `student`.`lastname` as `studentLastname`,
        `student`.`synergy_id` as `studentExternalId`,
        `staff`.`id` as `staffId`,
        `staff`.`title` as `staffTitle`,
        `staff`.`firstname` as `staffFirstname`,
        `staff`.`lastname` as `staffLastname`,
        `staff`.`synergy_id` as `staffExternalId`
 from ((`schoolbox`.`submission_return` `sr1`
     join `schoolbox`.`submission_box` `sb` on (`sr1`.`box_id` = `sb`.`id`))
     left join `schoolbox`.`submission_return` `sr2` on (
                 `sr1`.`box_id` = `sr2`.`box_id`
             and `sr1`.`owner` = `sr2`.`owner`
             and `sr1`.`review_route` = `sr2`.`review_route`
             and `sr2`.`published_at` is not null
             and `sr2`.`deleted_at` is null
             and `sr1`.`published_at` < `sr2`.`published_at`)
     LEFT JOIN `user` `student` on `sr1`.`owner` = `student`.`id`
     LEFT JOIN `user` `staff` on `sr1`.`marker` = `staff`.`id`)
 where `sr1`.`published_at` is not null
   and `sr1`.`deleted_at` is null
   and `sr2`.`id` is null
   and `sr1`.`review_route` = 'instructor'
   and `sb`.`assessment_id` in ({$bindings->getPlaceholder()})
 group by `sb`.`assessment_id`, `sr1`.`owner`, `sr1`.`review_route`, `sr1`.`published_at`)
union
(select `ass`.`assessment_id`   AS `assessment_id`,
        'assess_attempt'        AS `feedback_table`,
        max(`aa1`.`attempt_id`) AS `feedback_id`,
        `aa1`.`user_id`         AS `student_id`,
        `aa1`.`reviewed_at`     AS `completionDate`,
        null                    AS `comment`,
        `aa1`.`mark`            AS `mark`,
        (`aa1`.`mark` / `aa1`.`max_mark` * 100)    AS `norm_mark`,
        `student`.`title` as `studentTitle`,
        `student`.`firstname` as `studentFirstname`,
        `student`.`lastname` as `studentLastname`,
        `student`.`synergy_id` as `studentExternalId`,
        `staff`.`id` as `staffId`,
        `staff`.`title` as `staffTitle`,
        `staff`.`firstname` as `staffFirstname`,
        `staff`.`lastname` as `staffLastname`,
        `staff`.`synergy_id` as `staffExternalId`
 from ((`schoolbox`.`assess_attempt` `aa1`
     join `schoolbox`.`assess` `ass` on (`aa1`.`assess_id` = `ass`.`assess_id`))
     left join `schoolbox`.`assess_attempt` `aa2` on (
                 `aa1`.`assess_id` = `aa2`.`assess_id`
             and `aa1`.`user_id` = `aa2`.`user_id`
             and `aa2`.`reviewed_at` is not null
             and `aa2`.`deleted_at` is null
             and `aa1`.`reviewed_at` < `aa2`.`reviewed_at`)
     LEFT JOIN `user` `student` on `aa1`.`user_id` = `student`.`id`
     LEFT JOIN `user` `staff` on `aa1`.`reviewer_id` = `staff`.`id`)
 where `aa1`.`reviewed_at` is not null
   and `aa1`.`deleted_at` is null
   and `aa2`.`attempt_id` is null
   and `ass`.`assessment_id` is not null
   and `ass`.`assessment_id` in ({$bindings->getPlaceholder()})
 group by `ass`.`assessment_id`, `aa1`.`user_id`, `aa1`.`reviewed_at`)
union
(select `t`.`assessment_id`     AS `assessment_id`,
        'task_completion'       AS `feedback_table`,
        max(`tc1`.`id`)         AS `feedback_id`,
        `tc1`.`student_id`      AS `student_id`,
        `tc1`.`completion_date` AS `completionDate`,
        null                    AS `comment`,
        null                    AS `mark`,
        null                    AS `norm_mark`,
        `student`.`title` as `studentTitle`,
        `student`.`firstname` as `studentFirstname`,
        `student`.`lastname` as `studentLastname`,
        `student`.`synergy_id` as `studentExternalId`,
        `staff`.`id` as `staffId`,
        `staff`.`title` as `staffTitle`,
        `staff`.`firstname` as `staffFirstname`,
        `staff`.`lastname` as `staffLastname`,
        `staff`.`synergy_id` as `staffExternalId`
 from ((`schoolbox`.`task_completion` `tc1`
     join `schoolbox`.`task` `t` on (`tc1`.`task_id` = `t`.`id`))
     left join `schoolbox`.`task_completion` `tc2` on (
                 `tc1`.`task_id` = `tc2`.`task_id`
             and `tc1`.`student_id` = `tc2`.`student_id`
             and `tc1`.`completion_status` = `tc2`.`completion_status`
             and `tc2`.`deleted_at` is null
             and `tc1`.`completion_date` < `tc2`.`completion_date`)
     LEFT JOIN `user` `student` on `tc1`.`student_id` = `student`.`id`
     LEFT JOIN `user` `staff` on `tc1`.`marking_user_id` = `staff`.`id`)
 where `tc1`.`completion_date` is not null
   and `tc1`.`deleted_at` is null
   and `tc2`.`id` is null
   and `tc1`.`completion_status` = 3
   and `t`.`assessment_id` in ({$bindings->getPlaceholder()})
 group by `t`.`assessment_id`, `tc1`.`student_id`, `tc1`.`completion_date`)
union
(select `la`.`assessment_id`   AS `assessment_id`,
        'lti_assessment_score' AS `feedback_table`,
        max(`sc1`.`id`)        AS `feedback_id`,
        `sc1`.`student_id`     AS `student_id`,
        `sc1`.`created_at`     AS `completionDate`,
        `sc1`.`comment`        AS `comment`,
        `sc1`.`score_given`            AS `mark`,
        (`sc1`.`score_given` / `sc1`.`score_maximum` * 100)    AS `norm_mark`,
        `student`.`title` as `studentTitle`,
        `student`.`firstname` as `studentFirstname`,
        `student`.`lastname` as `studentLastname`,
        `student`.`synergy_id` as `studentExternalId`,
        `staff`.`id` as `staffId`,
        `staff`.`title` as `staffTitle`,
        `staff`.`firstname` as `staffFirstname`,
        `staff`.`lastname` as `staffLastname`,
        `staff`.`synergy_id` as `staffExternalId`
 from ((`schoolbox`.`lti_assessment_score` `sc1`
     join `schoolbox`.`lti_assessment` `la` on (`sc1`.`lti_assessment_id` = `la`.`id`))
     left join `schoolbox`.`lti_assessment_score` `sc2` on (
                 `sc1`.`lti_assessment_id` = `sc2`.`lti_assessment_id`
             and `sc1`.`student_id` = `sc2`.`student_id`
             and `sc2`.`created_at` is not null
             and `sc2`.`deleted_at` is null
             and `sc1`.`created_at` < `sc2`.`created_at`)
     LEFT JOIN `user` `student` on `sc1`.`student_id` = `student`.`id`
     LEFT JOIN `user` `staff` on `sc1`.`marking_user_id` = `staff`.`id`)
 where `sc1`.`created_at` is not null
   and `sc1`.`deleted_at` is null
   and `sc1`.`is_feedback` = 1
   and `sc2`.`id` is null
and `la`.`assessment_id` in ({$bindings->getPlaceholder()})
 group by `la`.`assessment_id`, `sc1`.`student_id`, `sc1`.`created_at`)
order by `assessment_id`, `student_id`;
            ", $bindings->getParams());
        $statement->execute();
        $feedbackItems = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!isset($feedbackItems[$row['assessment_id']])) {
                $feedbackItems[$row['assessment_id']] = [];
            }

            $feedbackItems[$row['assessment_id']][$row['student_id']] = [
                    'learner'        => [
                        'id'         => $row['student_id'],
                        'title'      => $row['studentTitle'],
                        'firstName'  => $row['studentFirstname'],
                        'lastName'   => $row['studentLastname'],
                        'externalId' => $row['studentExternalId'],
                    ],
                    'mark'           => $row['mark'],
                    'normalisedMark' => $row['norm_mark'],
                    'comment'        => $row['comment'],
                    'date'           => (new \Alaress_Date_Format($row['completionDate'], \Alaress_Date_Format::DATABASE_FORMAT))->toIso8601String(),
                    'instructor'     => [
                        'id'         => $row['staffId'],
                        'title'      => $row['staffTitle'],
                        'firstName'  => $row['staffFirstname'],
                        'lastName'   => $row['staffLastname'],
                        'externalId' => $row['staffExternalId'],
                    ],
                    'rubric'        => $rubric,
                ];
        }
        return $feedbackItems;
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
            INNER JOIN `rubric_descriptor` `rd` on (`rim`.`descriptor_id` = `rd`.`id` and `rd`.`deleted_at` is null)
            INNER JOIN `rubric_level` `rl` on (`rd`.`level_id` = `rl`.`id` and `sb`.`id` = `rl`.`box_id` and `rl`.`deleted_at` is null)
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
                    'value'                 => (float)$row['capability_mark'],
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
                    'descriptor'    => [
                        'id'            => (int)$row['descriptor_id'],
                        'description'   => $row['descriptor_name'],
                        'maxValue'      => (float)$row['descriptor_max_value'],
                        'sequence'      => (int)$row['descriptor_sequence']
                    ]
                ];
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
}

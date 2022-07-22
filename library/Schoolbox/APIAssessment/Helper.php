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
        SELECT
    assessment.id,
    COALESCE(
            task_completion.completion_date,
            submission_return.created_at,
            lti_assessment_score.created_at,
            assess_attempt.reviewed_at
        ) as `completionDate`,
    v_most_recent_feedback.student_id,
    student.title as `studentTitle`,
    student.firstname as `studentFirstname`,
    student.lastname as `studentLastname`,
    student.synergy_id as `studentExternalId`,

    COALESCE(submission_return.comment, lti_assessment_score.comment) as `comment`,
    COALESCE(submission_return.mark, lti_assessment_score.score_given, assess_attempt.mark) as `mark`,
    COALESCE(
            submission_return.norm_mark,
            (lti_assessment_score.score_given / lti_assessment_score.score_maximum * 100),
            (assess_attempt.mark / assess_attempt.max_mark * 100)
        ) as `norm_mark`,

    staff.id as `staffId`,
    staff.title as `staffTitle`,
    staff.firstname as `staffFirstname`,
    staff.lastname as `staffLastname`,
    staff.synergy_id as `staffExternalId`,
    v_most_recent_feedback.feedback_id as `feedbackID`
FROM v_assessment_relation
         INNER JOIN assessment on v_assessment_relation.assessment_id = assessment.id
         LEFT JOIN v_most_recent_feedback on assessment.id = v_most_recent_feedback.assessment_id

         LEFT JOIN user student on v_most_recent_feedback.student_id = student.id
         LEFT JOIN task_completion on (v_most_recent_feedback.feedback_id = task_completion.id AND v_most_recent_feedback.feedback_table = 'task_completion')
         LEFT JOIN submission_return on (v_most_recent_feedback.feedback_id = submission_return.id AND v_most_recent_feedback.feedback_table = 'submission_return')

         LEFT JOIN lti_assessment_score on (v_most_recent_feedback.feedback_id = lti_assessment_score.id AND v_most_recent_feedback.feedback_table = 'lti_assessment_score')

         LEFT JOIN assess_attempt on (v_most_recent_feedback.feedback_id = assess_attempt.attempt_id AND v_most_recent_feedback.feedback_table = 'assess_attempt')

         LEFT JOIN user staff on COALESCE(task_completion.marking_user_id, submission_return.marker, lti_assessment_score.marking_user_id, assess_attempt.reviewer_id) = staff.id
WHERE
v_most_recent_feedback.review_route = 'instructor' AND
    v_assessment_relation.assessment_id in ({$bindings->getPlaceholder()})", $bindings->getParams());
        $statement->execute();
        $feedbackItems = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rubric = $this->getRubricFeedback($row['id'], $row['feedbackID']);
            if (!isset($feedbackItems[$row['id']])) {
                $feedbackItems[$row['id']] = [];
            }

            $feedbackItems[$row['id']][$row['student_id']] = [
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
     */
    public function getRubric(string $assessmentId)
    {
        $statement = $this->mapper->getDatabase()->prepare("
        SELECT
            rd.id as `descriptor_id`,
            rd.max_value as `descriptor_max_value`,
            rd.description as `descriptor_descriptor`,
            rl.sequence as `descriptor_sequence`,
            t.*
        FROM v_rubric t
                INNER JOIN rubric_descriptor rd ON t.indicator_id = rd.rubric_indicator_id
                INNER JOIN rubric_level rl on rd.level_id = rl.id
        WHERE assessment_id =  :assessmentID
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
                    'id'            => $row['capability_id'],
                    'name'          => $row['capability_name'],
                    'indicators'    => []
                ];
            }
            //capability should already exist, so lets build the indicator for that capability
            if (!isset($output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']])) {
                $output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']] = [
                    'id'            => $row['indicator_id'],
                    'name'          => $row['indicator_name'],
                    'maxValue'      => $row['indicator_max_value'],
                    'descriptors'   => []
                ];
            }
            //indicator should already exist, so lets build the descriptor for that indicator for that capability
            if (!isset($output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']]['descriptors'][$row['descriptor_id']])) {
                $output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']]['descriptors'][$row['descriptor_id']] = [
                    'id'            => $row['descriptor_id'],
                    'description'   => $row['descriptor_descriptor'],
                    'maxValue'      => $row['descriptor_max_value'],
                    'sequence'      => $row['descriptor_sequence']
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
        /**
         * Output format:
    rubric: {
        capabilities: {
            id: 123,
            name: "Capability 1",
            indicators: {
                id: 123,
                name: "Spelling and Grammar",
                maxValue: 10.00,
                descriptors:
                    {
                        id: 321,
                        description: "Grammar and mechanics of sentences is mostly correct.",
                        max_value: 3
                    },
                    {
                        id: 322,
                        description: "Basic idea of sentences is understood.",
                        max_value: 2.0
                    }
                }
            }
        }
         */
    }

    /**
     * get feedback rubric
     */
    public function getRubricFeedback(string $assessmentId, int $feedbackID)
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
           `rim`.`descriptor_name`                               AS `descriptor_name`
        from ((((`schoolbox`.`v_rubric` `vr`
            join `schoolbox`.`submission_box` `sb`on (`vr`.`assessment_id` = `sb`.`assessment_id`))
            join `schoolbox`.`submission_return` `sr` on (`sb`.`id` = `sr`.`box_id`))
            join `schoolbox`.`rubric_capability_mark` `rcm` on (`rcm`.`return_id` = `sr`.`id` and `rcm`.`rubric_capability_id` =
                                                     `vr`.`capability_id`))
            left join `schoolbox`.`v_rubric_indicator_mark_extend` `rim` on (`rim`.`rubric_indicator_id` = `vr`.`indicator_id` and `rcm`.`id` = `rim`.`rubric_capability_mark_id`))
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
                    'id'            => $row['capability_id'],
                    'name'          => $row['capability_name'],
                    'mark'          => $row['capability_mark'],
                    'max_value'          => $row['capability_max_value'],
                    'human_readable_mark'          => $row['capability_human_readable_mark'],
                    'indicators'    => []
                ];
            }
            //capability should already exist, so lets build the indicator for that capability
            if (!isset($output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']])) {
                $output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']] = [
                    'id'            => $row['indicator_id'],
                    'name'          => $row['indicator_name'],
                    'maxValue'      => $row['indicator_max_value'],
                    'descriptors'   => []
                ];
            }
            //indicator should already exist, so lets build the descriptor for that indicator for that capability
            //indicator should already exist, so lets build the descriptor for that indicator for that capability
            if (!isset($output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']]['descriptors'][$row['descriptor_id']])) {
                $output['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']]['descriptors'][$row['descriptor_id']] = [
                    'id'          => $row['descriptor_id'],
                    'description' => $row['descriptor_name'],
                    'mark'      => $row['indicator_mark'],
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
        /**
         * Output format:
        rubric: {
            "capabilities": {
                "id": "19",
                "name": "Grammar",
                "mark": "2.00000000000",
                "max_value": "4.00000000000",
                "human_readable_mark": "50 %",
                "indicators": {
                    "id": "37",
                    "name": "Coherence",
                    "maxValue": "4.00000000000",
                    "descriptors": {
                        "id": "223",
                        "description": "Grammar and mechanics of sentences is mostly correct.",
                        "mark": "2.00000000000"
                    }
                }
            }
        }
         */
    }
}

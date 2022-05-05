<?php

namespace Schoolbox\APIAssessment;

use Schoolbox\GenericCollection;
use Schoolbox\UserMapper;
use PDO;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;
use Schoolbox\YearLevel\Model as YearLevelModel;

class Helper
{
    /**
     * @var GenericCollection | YearLevelModel[] | null
     */
    protected GenericCollection | YearLevelModel | null $yearLevels = null;

    protected YearLevelMapper $mapper;

    /**
     *
     */
    public function __construct(YearLevelMapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * @param string $assessmentId
     * @return array
     */
    public function getAssessment(string $assessmentId)
    {
        $query  = "
SELECT
    assessment.id, assessment.title, assessment.weight,
    assessment.course_managed as `commonAssessment`,
    lu_work_type.name AS `workType`,
    v_assessment_relation.folder_id as `folderId`,
    markTypeTable.hr_name AS markType,
    COALESCE(
            if(task.id, 'Task', null),
            if(assess.assessment_id, 'Quiz', null),
            if(lti_assessment.assessment_id, 'LTI', null),
            if(submission_box.id, 'DueWork', null),
            if(project.id, 'Project', null)
        ) as `assessmentType`,

    COALESCE(task_completion.completion_date, submission_return.created_at) as `completionDate`,
    v_most_recent_feedback.feedback_table, v_most_recent_feedback.feedback_id, v_most_recent_feedback.student_id,
    v_most_recent_feedback.review_route as `reviewRoute`,
    student.title as `studentTitle`,
    student.firstname as `studentFirstname`,
    student.lastname as `studentLastname`,
    student.synergy_id as `studentExternalId`,

    COALESCE(submission_return.comment, lti_assessment_score.comment) as `comment`,
    COALESCE(submission_return.mark, lti_assessment_score.score_given) as `mark`,
    submission_return.norm_mark,

    staff.id as `staffId`,
    staff.title as `staffTitle`,
    staff.firstname as `staffFirstname`,
    staff.lastname as `staffLastname`,
    staff.synergy_id as `staffExternalId`,
    folder_code.code as `folderCode`,
    folder.name as `folderName`,
    (SELECT group_concat(id) from lu_year_level
            LEFT JOIN folder_year_level on folder_year_level.year_level_id= lu_year_level.id
                    where folder_year_level.folder_id = v_assessment_relation.folder_id) as `yearLevelId`


FROM v_assessment_relation

         JOIN assessment on v_assessment_relation.assessment_id = assessment.id
         LEFT JOIN v_most_recent_feedback on assessment.id = v_most_recent_feedback.assessment_id
         LEFT JOIN submission_box ON assessment.id = submission_box.assessment_id
         LEFT JOIN submission_mark_type as markTypeTable ON submission_box.mark_type = markTypeTable.mark_type_id


         LEFT JOIN lu_work_type ON assessment.work_type_id = lu_work_type.id

         LEFT JOIN task_completion on (v_most_recent_feedback.feedback_id = task_completion.id AND v_most_recent_feedback.feedback_table = 'task_completion')

         LEFT JOIN submission_return on (v_most_recent_feedback.feedback_id = submission_return.id AND v_most_recent_feedback.feedback_table = 'submission_return')

         LEFT JOIN lti_assessment_score on (v_most_recent_feedback.feedback_id = lti_assessment_score.id AND v_most_recent_feedback.feedback_table = 'lti_assessment_score')

         LEFT JOIN assess_attempt on (v_most_recent_feedback.feedback_id = assess_attempt.attempt_id AND v_most_recent_feedback.feedback_table = 'assess_attempt')

         LEFT JOIN user student on v_most_recent_feedback.student_id = student.id

         LEFT JOIN user staff on COALESCE(task_completion.marking_user_id, submission_return.marker, lti_assessment_score.marking_user_id, assess_attempt.reviewer_id) = staff.id

         LEFT JOIN task on assessment.id = task.assessment_id
         LEFT JOIN assess on assessment.id = assess.assessment_id
         LEFT JOIN lti_assessment on assessment.id = lti_assessment.assessment_id
         LEFT JOIN project on assessment.id = project.assessment_id
         LEFT JOIN folder on v_assessment_relation.folder_id = folder.id
         LEFT JOIN folder_code on (v_assessment_relation.folder_id = folder_code.folder_id and folder_code.deleted_at is null)
WHERE
        v_assessment_relation.assessment_id = :id";

        $mapper = UserMapper::getInstance()->getDatabase();
        $statement = $mapper->prepare($query);
        $statement->bindValue('id', $assessmentId);

        $statement->execute();
        $output = [];
        $output['participants'] = [];
        /**
         * build the whole thing
         */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $completionDate = new \Alaress_Date_Format($row['completionDate'], \Alaress_Date_Format::DATABASE_FORMAT);
            $output['id'] = $row['id'];
            $output['assessmentType'] = $row['assessmentType'];
            $output['title'] = $row['title'];
            $output['weight'] = $row['weight'];
            $output['commonAssessment'] = (bool)$row['commonAssessment'];
            $output['workType'] = $row['workType'];
            $output['markType'] = $row['markType'];
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
            $output['folder'] = [
                'id' => $row['folderId'],
                'name' => $row['folderName'],
                'code' => $row['folderCode'],
                'yearLevel' => $yearLevel,
            ];
            if (null !== $row['student_id'] && 'instructor' ===  $row['reviewRoute']) {
                $output['participants'][] = [
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
                    'date'           => $completionDate->toIso8601String(),
                    'instructor'     => [
                        'id'         => $row['staffId'],
                        'title'      => $row['staffTitle'],
                        'firstName'  => $row['staffFirstname'],
                        'lastName'   => $row['staffLastname'],
                        'externalId' => $row['staffExternalId'],
                    ]

                ];
            }
        }
        return $output;
    }

    /**
     *
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
            $lim = $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        }
        if (0 !== $cursor) {
            $cur = $statement->bindValue('cursor', $cursor, PDO::PARAM_INT);
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
            if ('0.00' !== $row['weighted']) {
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
                'code' => $row['folderCode'],
                'yearLevel' => $yearLevel,
            ];
            $output[] = [
                'id'  => $row['id'],
                'title'         => $row['title'],
                'assessmentType' => $row['assessmentType'],
                'commonAssessment' => (bool)$row['commonAssessment'],
                'workType'      => [
                    'name'  => $row['workTypeName'],
                    'id'    => $row['workTypeId']
                ],
                'folder'        => $folder,
                'weighted'      => $weighted,
                'dueDate'      => $row['due_date'],

            ];
        }
        return $output;
    }

    /**
     *
     */
    public function searchForAssessmentsCount(array $filter): int
    {
        [$query, $params] = $this->buildSearch($filter);
        $statement = $this->mapper->getDatabase()->prepare($query);
        foreach ($params as $label => $value) {
            $statement->bindValue($label, $value);
        }
        $statement->execute();
        $count = 0;
        /**
         * build the whole thing
         */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $count++;
        }
        return $count;
    }

    /**
     * search base
     * build the search query for both count, and json response
     */
    private function buildSearch(array $filter)
    {
        $params = [];

        $select  = "
SELECT
    assessment.id,
    assessment.title,
    lu_work_type.name as `workTypeName`,
    lu_work_type.id as `workTypeId`,
    v_assessment_relation.folder_id as `folderId`,
    assessment.weight as `weighted`,
    assessment.course_managed AS commonAssessment,
     COALESCE(
            if(task.id, 'Task', null),
            if(assess.assessment_id, 'Quiz', null),
            if(lti_assessment.assessment_id, 'LTI', null),
            if(submission_box.id, 'DueWork', null),
            if(project.id, 'Project', null)
        ) as `assessmentType`,
    assessment.due_date,
    folder_code.code as `folderCode`,
    folder.name as `folderName`,
    (SELECT group_concat(id) from lu_year_level
            LEFT JOIN folder_year_level on folder_year_level.year_level_id= lu_year_level.id
                    where folder_year_level.folder_id = v_assessment_relation.folder_id) as `yearLevelId`
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
LEFT JOIN folder_code on (v_assessment_relation.folder_id = folder_code.folder_id and folder_code.deleted_at is null)



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
        if (array_key_exists('folderId', $filter)) {
            $where .= " AND v_assessment_relation.folder_id = :folderId ";
            $params['folderId'] = $filter['folderId'];
        }
        if (array_key_exists('weighted', $filter)) {
            $where .= " AND assessment.weight";
            if ($filter['weighted'] === true) {
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
                $where             .= " AND due_date >= :dueFrom ";
                $params['dueFrom'] = $filter['dueDate']['from'];
            }
            if (array_key_exists('to', $filter['dueDate'])) {
                $where             .= " AND due_date <= :dueTo ";
                $params['dueTo'] = $filter['dueDate']['to'];
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
        return [
            $select . $from . $where,
            $params
        ];
    }

    /**
     * get nice yearlevel objects
     */
    public function getYearlevel(int $yearLevelId)
    {
        if (null === $this->yearLevels) {
            $this->yearLevels = $this->mapper->findAll()->indexBy('id');
        }
        return $this->yearLevels[$yearLevelId];
    }
}

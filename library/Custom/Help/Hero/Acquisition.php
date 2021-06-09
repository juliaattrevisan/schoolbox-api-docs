<?php

/**
 * Acquisition
 *
 * Get all the data we'll be operating on for Hero qualification
 * We can combine some data here; this class focuses on obtaining the correct data in a convenient structure
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Help\Hero;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Quoter\IQuoter;
use Alaress\IDatabase;
use Schoolbox\Permission\Access\UserAccess;

class Acquisition
{

    /**
     * Course Specification Id
     * This comes from the /course/modify screen, as is an integer in the url
     */
    public const HERO_COURSE_SPEC_ID = 23;

    /**
     * Mega-join common to both Due Work and Quizzes
     *
     * @var string
     */
    private $commonJoin = '
        INNER JOIN userAccess ua ON ua.uid = u.`id`
        INNER JOIN course_implementation ci ON ua.sid = ci.`folder_id` AND ua.service = :homepageService
        INNER JOIN course_specification cs ON ci.`course_specification_id` = cs.`id`
        INNER JOIN course_unit_implementation cui ON cui.`course_implementation_id` = ci.`id`
        INNER JOIN assessment_course_unit_implementation acui ON acui.course_unit_implementation_id = cui.`id`
        INNER JOIN project p ON acui.`assessment_id` = p.`assessment_id`
        INNER JOIN project_assessment pa ON pa.`project_id` = p.`id`
        INNER JOIN assessment a ON pa.`assessment_id` = a.`id`
        INNER JOIN assessment_specification_implementation asi ON a.`id` = asi.`implementation_assessment_id`
        INNER JOIN folder f ON ci.`folder_id` = f.`id`
    ';

    /**
     * Builder expects an IQuoter: pass in `getQuoter` from this object
     *
     * @var IDatabase
     */
    private $db;

    /**
     * Acquisition constructor.
     *
     * @param IDatabase $db
     */
    public function __construct(IDatabase $db)
    {
        $this->db = $db;
    }

    /**
     * Combine the hierarchies of Due Work and Quiz into one big stack
     *
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \TypeError
     * @throws \UnderflowException
     */
    public function findInvolvement(): array
    {
        $stack1 = $this->findDueWork();
        $stack2 = $this->findQuiz();

        /* Combine stack2 into stack1
         *
         * I guess there's no particular order required but if it really gets on your nerves
         * you could additionally sort on specId
         */
        foreach ($stack2 as $id => $data) {
            if (array_key_exists($id, $stack1)) {
                $stack1[$id] = array_merge($stack1[$id], $data);
            } else {
                $stack1[$id] = $data;
            }
        }

        return $stack1;
    }

    /**
     * Dynamically assemble WHERE clause
     *
     * Shared between Due Work and Quizzes pieces
     *
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \TypeError
     */
    private function makeDefaultBuilder(): Builder
    {
        $pb = new Builder($this->db->getQuoter(), 'upd');
        $pb->equals('cs.id', self::HERO_COURSE_SPEC_ID);
        $pb->isNull('cs.deleted_at');
        $pb->isNull('ci.deleted_at');
        $pb->isNull('cui.deleted_at');
        $pb->isNull('p.deleted_at');
        $pb->isNull('a.deleted_at');
        $pb->notEquals('a.created_at', '0000-00-00 00:00:00');
        $pb->isNull('u.deleted_at');
        $pb->isNull('f.deleted_at');

        return $pb;
    }

    /**
     * Find all the assessments and any Feedbacks that exist for them
     * If there are Feedbacks, find the one with the maximum normalised mark
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \TypeError
     * @throws \UnderflowException
     */
    private function findDueWork(): array
    {
        $pb = $this->makeDefaultBuilder();
        $pb->in('asi.specification_assessment_id', ValidQualification::MANDATORY_DUE_WORKS);
        $pb->notNull('sr.published_at');

        $where = implode("\nAND ", $pb->getWhere());

        /* Find all the Due Works and their 100% Feedbacks
         * This is a list of all the people who qualify for Hero status
         */
        $query = "
            SELECT
                u.id AS userId,
                u.firstname,
                u.lastname,
                f.id AS folderId,
                f.name AS folderName,
                asi.specification_assessment_id AS specId,
                MAX(sr.norm_mark) AS mark
            FROM user u
            {$this->commonJoin}
            INNER JOIN submission_box sb ON sb.`assessment_id` = a.`id`
            INNER JOIN submission_return sr ON sb.`id` = sr.`box_id` AND sr.`owner` = ua.`uid`
            WHERE {$where}
            GROUP BY a.id, sr.owner
            ORDER BY u.id, u.lastname, asi.specification_assessment_id
        ";

        /* Use a totally random mapper
         */
        $statement = $this->db->prepare($query);

        $statement->execute(array_merge([
            'homepageService' => UserAccess::SERVICE_HOMEPAGE,
        ], $pb->getParams()));

        $hierarchy = [];

        /* A hashtable of tuples for a user, their highest-marked Feedback for each of the mandatory assessments
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $hierarchy[$row['userId']][] = $row;
        }

        return $hierarchy;
    }

    /**
     * Find all the assessments and any Marked Attempts that exist for them
     * If there are Marked Attempts, find the one with the maximum normalised mark
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \TypeError
     * @throws \UnderflowException
     */
    private function findQuiz(): array
    {
        $availableMarks = $this->findQuizMaxMarks();

        $pb = $this->makeDefaultBuilder();
        $pb->in('asi.specification_assessment_id', array_merge(ValidQualification::MANDATORY_QUIZZES, ValidQualification::CONDITIONAL_QUIZZES));
        $pb->isNull('ass.deleted_at');
        $pb->notNull('aa.reviewed_at');
        $pb->isNull('aa.deleted_at');
        $pb->isNull('ar.deleted_at');

        $where = implode("\nAND ", $pb->getWhere());

        /* Find all the Quizzes and their 100% Attempts
         * This is a list of all the people who qualify for Hero status
         *
         * Unfortunately need to do postprocessing, because the attempts' totals are derived and not cached anywhere
         */
        $query = "
            SELECT
                u.id AS userId,
                u.firstname,
                u.lastname,
                f.id AS folderId,
                f.name AS folderName,
                asi.specification_assessment_id AS specId,
                ar.attempt_id AS attemptId,
                SUM(ar.mark) AS mark
            FROM `user` u
            {$this->commonJoin}
            INNER JOIN assess ass ON a.`id` = ass.`assessment_id`
            INNER JOIN assess_attempt aa ON ass.`assess_id` = aa.`assess_id` AND aa.`user_id` = u.`id`
            INNER JOIN assess_response ar ON aa.`attempt_id` = ar.`attempt_id`
            WHERE {$where}
            GROUP BY ar.attempt_id
            ORDER BY u.id, u.lastname, asi.specification_assessment_id, ar.attempt_id
        ";

        /* Use a totally random mapper
         */
        $statement = $this->db->prepare($query);

        $statement->execute(array_merge([
            'homepageService' => UserAccess::SERVICE_HOMEPAGE,
        ], $pb->getParams()));

        $hierarchy = [];

        /* A hashtable of tuples for a user, their highest-marked Feedback for each of the mandatory assessments
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /* Figure out the normalised mark for each attempt
             */
            $normalisedMark = 100.00000 * (float)$row['mark'] / (float)$availableMarks[$row['userId']][$row['attemptId']]['availableMarks'];

            $row['mark'] = $normalisedMark;

            $hierarchy[$row['userId']][] = $row;
        }

        return $hierarchy;
    }


    /**
     * Figure out the maximum marks available in each attempt
     *
     * Due to randomised questions this maximum mark might vary
     *
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \TypeError
     * @throws \UnderflowException
     */
    private function findQuizMaxMarks(): array
    {
        $pb = $this->makeDefaultBuilder();
        $pb->in('asi.specification_assessment_id', array_merge(ValidQualification::MANDATORY_QUIZZES, ValidQualification::CONDITIONAL_QUIZZES));
        $pb->isNull('ass.deleted_at');
        $pb->notNull('aa.reviewed_at');
        $pb->isNull('aa.deleted_at');
        $pb->isNull('aq.deleted_at');
        $pb->isNull('ar.deleted_at');

        $where = implode("\nAND ", $pb->getWhere());

        /* Find all the Quizzes and the Questions contained in each Attempt
         */
        $query = "
            SELECT
                u.id AS userId,
                u.firstname,
                u.lastname,
                f.id AS folderId,
                f.name AS folderName,
                asi.specification_assessment_id AS specId,
                ar.attempt_id AS attemptId,
                SUM(aq.marks) AS availableMarks
            FROM `user` u
            {$this->commonJoin}
            INNER JOIN assess ass ON a.`id` = ass.`assessment_id`
            INNER JOIN assess_attempt aa ON ass.`assess_id` = aa.`assess_id` AND aa.`user_id` = u.`id`
            INNER JOIN assess_response ar ON aa.`attempt_id` = ar.`attempt_id`
            INNER JOIN assess_question aq ON ar.`question_id` = aq.`question_id`
            WHERE {$where}
            GROUP BY ar.attempt_id
            ORDER BY u.id, u.lastname, asi.specification_assessment_id, ar.attempt_id
        ";

        /* Use a totally random mapper
         */
        $statement = $this->db->prepare($query);

        $statement->execute(array_merge([
            'homepageService' => UserAccess::SERVICE_HOMEPAGE,
        ], $pb->getParams()));

        $hierarchy = [];

        /* A hashtable of tuples for a user, their highest-marked Feedback for each of the mandatory assessments
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $hierarchy[$row['userId']][$row['attemptId']] = $row;
        }

        return $hierarchy;
    }
}

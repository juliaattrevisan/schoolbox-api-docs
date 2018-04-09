<?php
/**
 * Acquisition
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Help\Hero;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;

class Acquisition
{

    /**
     * Course Specification Id
     * This comes from the /course/modify screen, as is an integer in the url
     */
    public const HERO_COURSE_SPEC_ID = 23;

    /**
     * Due Work
     *
     * A list of assessment spec ids that are mandatory for the Hero award
     * There are some optional Due Works that don't contribute toward the achievement
     */
    private $mandatoryDueWorks = [
        15574, // Unit 4, Project 3 Advanced Course Skills
        15607, // Unit 5, Project 2 Master Courses And Classes
        15635, // Unit 7, Project 3 Managing Groups And Pages
    ];

    /**
     * Quizzes
     *
     * A list of assessment spec ids that are mandatory for the Hero award
     */
    private $mandatoryQuizzes = [
        16058, // Unit 1, Project 2 Prove yourself as a Schoolbox Teacher Newbie
        15912, // Unit 2, Project 1 Review your knowledge of the calendar
        15578, // Unit 2, Project 2 Verify your understanding of news
        15579, // Unit 2, Project 2 Verify your understanding of viewing and posting news
        15767, // Unit 3, Project 1 Assess your knowledge of the timetable
        15766, // Unit 3, Project 1 Assess your knowledge of the timetable and marking attendance
        15509, // Unit 3, Project 2 Verify your knowledge of page types and components
        15768, // Unit 6, Project 1 Prove your knowledge about submitting and marking
        16911, // Unit 7, Project 1 Test your knowledge of advanced component usage
        15913, // Unit 7, Project 2 Test your knowledge of creating pages
        15772, // Unit 8, Project 1 Complete this continuous reporting quiz
        43618, // Unit 9 Review your knowledge of v17
        46375, // Unit 9 Review your knowledge of v17.5
    ];

    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * Acquisition constructor.
     *
     * @param UserMapper            $userMapper
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \TypeError
     * @throws \UnderflowException
     */
    public function __construct(UserMapper $userMapper) {
        $this->courseSpecId = self::HERO_COURSE_SPEC_ID;
        $this->userMapper = $userMapper;
    }

    /**
     * Number of assessments that are required to get Hero status
     *
     * @return int
     */
    public function numRequired(): int
    {
        return \count($this->mandatoryDueWorks) + \count($this->mandatoryQuizzes);
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
            }
            else {
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
    private function makeDefaultBuilder()
    {
        $pb = new Builder($this->userMapper->getDatabase(), 'upd');
        $pb->equals('cs.id', $this->courseSpecId);
        $pb->isNull('cs.deleted_at');
        $pb->isNull('ci.deleted_at');
        $pb->isNull('cui.deleted_at');
        $pb->isNull('p.deleted_at');
        $pb->isNull('a.deleted_at');
        $pb->notEquals('a.created_at', '0000-00-00 00:00:00');
        $pb->isNull('u.deleted_at');

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
        $pb->in('asi.specification_assessment_id', $this->mandatoryDueWorks);
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
                asi.specification_assessment_id AS specId,
                a.id AS assessmentId, 
                a.title AS assessmentTitle,
                MAX(sr.norm_mark) AS mark
            FROM user u
            INNER JOIN userAccess ua ON ua.uid = u.`id`
            INNER JOIN course_implementation ci ON ua.sid = ci.`folder_id` AND ua.service = :homepageService
            INNER JOIN course_specification cs ON ci.`course_specification_id` = cs.`id`
            INNER JOIN course_unit_implementation cui ON cui.`course_implementation_id` = ci.`id`
            INNER JOIN assessment_course_unit_implementation acui ON acui.course_unit_implementation_id = cui.`id`
            INNER JOIN project p ON acui.`assessment_id` = p.`assessment_id`
            INNER JOIN project_assessment pa ON pa.`project_id` = p.`id`
            INNER JOIN assessment a ON pa.`assessment_id` = a.`id`
            INNER JOIN assessment_specification_implementation asi ON a.`id` = asi.`implementation_assessment_id`
            INNER JOIN submission_box sb ON sb.`assessment_id` = a.`id`
            INNER JOIN submission_return sr ON sb.`id` = sr.`box_id` AND sr.`owner` = ua.`uid`
            WHERE {$where}
            GROUP BY a.id, sr.owner
            ORDER BY u.id, u.lastname, asi.specification_assessment_id
        ";

        /* Use a totally random mapper
         */
        $statement = $this->userMapper->getDatabase()->prepare($query);

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
        $pb = $this->makeDefaultBuilder();
        $pb->in('asi.specification_assessment_id', $this->mandatoryQuizzes);
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
                asi.specification_assessment_id AS specId,
                a.id AS assessmentId, 
                a.title AS assessmentTitle,
                ar.attempt_id AS attemptId,
                SUM(ar.mark) AS mark
            FROM `user` u
            INNER JOIN userAccess ua ON ua.uid = u.`id`
            INNER JOIN course_implementation ci ON ua.sid = ci.`folder_id` AND ua.service = :homepageService
            INNER JOIN course_specification cs ON ci.`course_specification_id` = cs.`id`
            INNER JOIN course_unit_implementation cui ON cui.`course_implementation_id` = ci.`id`
            INNER JOIN assessment_course_unit_implementation acui ON acui.course_unit_implementation_id = cui.`id`
            INNER JOIN project p ON acui.`assessment_id` = p.`assessment_id`
            INNER JOIN project_assessment pa ON pa.`project_id` = p.`id`
            INNER JOIN assessment a ON pa.`assessment_id` = a.`id`
            INNER JOIN assessment_specification_implementation asi ON a.`id` = asi.`implementation_assessment_id`
            INNER JOIN assess ass ON a.`id` = ass.`assessment_id`
            INNER JOIN assess_attempt aa ON ass.`assess_id` = aa.`assess_id` AND aa.`user_id` = u.`id`
            INNER JOIN assess_response ar ON aa.`attempt_id` = ar.`attempt_id`
            WHERE {$where}
            GROUP BY ar.attempt_id
            ORDER BY u.id, u.lastname, asi.specification_assessment_id, ar.attempt_id
        ";

        /* Use a totally random mapper
         */
        $statement = $this->userMapper->getDatabase()->prepare($query);

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
}
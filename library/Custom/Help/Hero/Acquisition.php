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
     * A list of assessment spec ids that are mandatory for the Hero award
     * There are some optional Due Works that don't contribute toward the achievement
     */
    private $mandatoryDueWorks = [
        15574, // Unit 4, Project 3 Advanced Course Skills
        15607, // Unit 5, Project 2 Master Courses And Classes
        15635, // Unit 7, Project 3 Managing Groups And Pages
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
     * Find all the assessments and any Feedbacks that exist for them
     * If there are Feedbacks, find the one with the maximum normalised mark
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \TypeError
     * @throws \UnderflowException
     */
    public function findInvolvement(): array
    {
        /* Dynamically assemble WHERE clause
         */
        $pb = new Builder($this->userMapper->getDatabase(), 'upd');
        $pb->equals('cs.id', $this->courseSpecId);
        $pb->isNull('cs.deleted_at');
        $pb->isNull('ci.deleted_at');
        $pb->isNull('cui.deleted_at');
        $pb->isNull('p.deleted_at');
        $pb->isNull('a.deleted_at');
        $pb->notEquals('a.created_at', '0000-00-00 00:00:00');
        $pb->isNull('u.deleted_at');
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
                MAX(sr.norm_mark) AS highestMark
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
}
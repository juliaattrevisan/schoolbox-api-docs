<?php
/**
 * Comments
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Diocesan\AchievementReport\Comments;

use Custom\Diocesan\AchievementReport\NormaliseTeachers;


class Comments extends Base
{
    public function setup()
    {
    }


    /**
     * Initialised array of term indices
     */
    private const INITIAL_TERMS = [
        1 => null,
        2 => null,
        3 => null,
        4 => null
    ];

    /**
     * Get all the comments for a given user, ordered by year then term
     *
     * ResultType indicates the report each comment is associated with
     *
     * SG: School Grades
     * NCEA: NCEA
     * IB: IB
     *
     * @return array
     */
    public function findData(): array
    {
        $query = "
            SELECT 
                *
            FROM dbo.uvSchoolBoxReportComments
            WHERE id = '{$this->getExternalUserId()}'
            AND StudentYear = '{$this->year}'
            ORDER BY CourseDescription, Term
        ";

        $result = $this->getDb()->getAll($query) ?: [];

        /* Special structure for Junior School Students
         */
        if ((new ValidJuniorSchoolStudent())->isSatisfiedBy($this->user)) {
            return $this->formatJuniorSchoolData($result);
        }

        return $this->formatSeniorSchoolData($result);
    }


    /**
     * Get comments in the Junior School format
     *
     * @param array $result
     * @return array
     */
    private function formatJuniorSchoolData(array $result): array
    {
        $comments = [];

        $teacherNormaliser = new NormaliseTeachers();

        /* TODO: Reformat this according to a spec they provided
         * but the data doesn't quite match all the way
         */
        foreach ($result as $current) {
            $year = $current['StudentYear'];
            $courseName = $current['CourseDescription'];
            $term = $current['Term'];
            $comment = $current['Comment'];

            /* Initialise an array of Terms
             */
            if (!isset($comments[$year]['courses'][$courseName])) {
                $comments[$year]['courses'][$courseName]['terms'] = self::INITIAL_TERMS;
            }

            $comments[$year]['courses'][$courseName]['terms'][$term] = [
                'comment' => $comment,
                'teachers' => $teacherNormaliser->normalise($current)
            ];
        }

        return $comments;
    }


    /**
     * Get comments in the Senior School format
     *
     * @param array $result
     * @return array
     */
    private function formatSeniorSchoolData(array $result): array
    {
        $comments = [];

        $teacherNormaliser = new NormaliseTeachers();

        foreach ($result as $current) {
            $year = $current['StudentYear'];
            $courseName = $current['CourseDescription'];
            $term = $current['Term'];
            $comment = $current['Comment'];

            /* Initialise an array of Terms
             */
            if (!isset($comments[$year]['courses'][$courseName])) {
                $comments[$year]['courses'][$courseName]['terms'] = self::INITIAL_TERMS;
            }

            $comments[$year]['courses'][$courseName]['terms'][$term] = [
                'comment' => $comment,
                'teachers' => $teacherNormaliser->normalise($current)
            ];
        }

        return $comments;
    }
}
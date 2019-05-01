<?php
/**
 * Comments
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Diocesan\AchievementReport\Comments;

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
                StudentYear,
                Term,
                ClassCode,
                CourseDescription,
                Comment,
                ClassTeacher1,
                ClassTeacher1Email,
                ClassTeacher2,
                ClassTeacher2Email,
                ClassTeacher3,
                ClassTeacher3Email,
                ResultType
            FROM dbo.uvSchoolBoxReportComments
            WHERE id = '{$this->getExternalUserId()}'
            ORDER BY StudentYear, CourseDescription, Term
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
        /* Reorganise in the Junior Students' format
         */

        return [];
    }


    /**
     * Get comments in the Senior School format
     *
     * @param array $result
     * @return array
     */
    private function formatSeniorSchoolData(array $result): array
    {

        /* Reorganise in the Senior Students' format
         */
        $comments = [];

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

            $comments[$year]['courses'][$courseName]['terms'][$term] = $comment;
        }

        new \Alaress_Probe($comments);

        return [];
    }
}
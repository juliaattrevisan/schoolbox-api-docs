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

    /**
     * Initialised array of term indices
     */
    public const INITIAL_TERMS = [
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
        /* Special structure for Junior School Students
        */
        if ((new ValidJuniorSchoolStudent())->isSatisfiedBy($this->user)) {
            return $this->findJuniorSchoolData();
        }

        return $this->findSeniorSchoolData();
    }


    /**
     * Get all the comments for a given user, ordered by year then term
     *
     * ResultType indicates the report each comment is associated with
     *
     * Junior School uses SortOrder to sequence their subjects
     *
     * SG: School Grades
     * NCEA: NCEA
     * IB: IB
     *
     * @return array
     */
    private function findJuniorSchoolData(): array
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
            AND StudentYear = '{$this->year}'
            ORDER BY SortOrder, CourseDescription, Term
        ";

        $result = $this->getDb()->getAll($query) ?: [];

        return $this->formatData($result);

    }


    /**
     * Get all the comments for a given user, ordered by year then term
     *
     * ResultType indicates the report each comment is associated with
     *
     * Senior School uses alphabetical CourseDescription to sequence their subjects
     *
     * SG: School Grades
     * NCEA: NCEA
     * IB: IB
     *
     * @return array
     */
    private function findSeniorSchoolData(): array
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
            AND StudentYear = '{$this->year}'
            ORDER BY CourseDescription, Term
        ";

        $result = $this->getDb()->getAll($query) ?: [];

        return $this->formatData($result);
    }


    /**
     * Restructure flat array into hierarchy
     *
     * @param array $result
     * @return array
     */
    private function formatData(array $result): array
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

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
            ORDER BY StudentYear, Term 
        ";

        return $this->getDb()->getAll($query) ?: [];
    }
}
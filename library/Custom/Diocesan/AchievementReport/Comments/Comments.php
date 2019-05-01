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
     * This is the Junior School Students campus code
     */
    private const CAMPUS_JUNIOR_SCHOOL = 'JNR';

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

        $result = $this->getDb()->getAll($query) ?: [];

        /* Special structure for Junior School Students
         */
        if ($this->isJuniorSchoolStudent()) {
            return $this->formatJuniorSchoolData($result);
        }

        return $this->formatSeniorSchoolData($result);
    }


    /**
     * Determine if this->user is a Junior School Student by looking at
     * their campus involvement
     *
     * @return bool
     */
    public function isJuniorSchoolStudent(): bool
    {
        return null !== $this->user->getCampusCollection()->getBy('extTimetableCode', self::CAMPUS_JUNIOR_SCHOOL);
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

        return [];
    }
}
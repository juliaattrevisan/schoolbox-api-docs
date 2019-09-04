<?php
/**
 * Comments
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Diocesan\AchievementReport\Comments;

use Custom\Diocesan\AchievementReport\NormaliseTeachers;


class Comments extends Base
{

    /**
     * Consts corresponding to ResultType (that's synonymous with a report type)
     * Each comment belongs to one of these reports
     */
    private const REPORT_TYPE_IB = 'IB';
    private const REPORT_TYPE_NCEA = 'NCEA';
    private const REPORT_TYPE_SCHOOLGRADES = 'SG';


    /**
     * Get all the comments for a given user, ordered by year then term
     *
     * ResultType indicates the report each comment is associated with
     * Use this method to get all the comments for every report everywhere
     *
     * SG: School Grades
     * NCEA: NCEA
     * IB: IB
     *
     * @return array
     */
    public function findData(): array
    {
        return $this->findCommentsForReport(null);
    }


    /**
     * Find NCEA Comments
     *
     * @return array
     */
    public function findNCEAData(): array
    {
        return $this->findCommentsForReport(self::REPORT_TYPE_NCEA);
    }


    /**
     * Find School Grades Comments
     *
     * @return array
     */
    public function findSchoolGradesData(): array
    {
        return $this->findCommentsForReport(self::REPORT_TYPE_SCHOOLGRADES);
    }


    /**
     * Find IB Comments
     *
     * @return array
     */
    public function findIBData(): array
    {
        return $this->findCommentsForReport(self::REPORT_TYPE_IB);
    }


    /**
     * Get the comments for a particular report
     *
     * @param null|string $resultType
     * @return array
     */
    private function findCommentsForReport(?string $resultType): array
    {
        /* Special structure for Junior School Students
         */
        if ((new ValidJuniorSchoolStudent())->isSatisfiedBy($this->user)) {
            return $this->findJuniorSchoolData($resultType);
        }

        return $this->findSeniorSchoolData($resultType);
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
     * @param string|null $resultType
     * @return array
     */
    private function findJuniorSchoolData(?string $resultType): array
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
        ";

        if (null !== $resultType) {
            $query .= " AND type = '{$resultType}' ";
        }

        $query .= '
            ORDER BY SortOrder, CourseDescription, Term
        ';

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
     * @param string|null $resultType
     * @return array
     */
    private function findSeniorSchoolData(?string $resultType): array
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
        ";

        if (null !== $resultType) {
            $query .= " AND ResultType = '{$resultType}' ";
        }

        $query .= '
            ORDER BY CourseDescription, Term
        ';

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
            $classCode = $current['ClassCode'];

            /* Comment data
             */
            $data = [
                'comment' => $comment,
                'teachers' => $teacherNormaliser->normalise($current)
            ];

            /* Initialise an array of Terms
             *
             * The Comments screen is grouped by classCode
             * Other screens (School Grades, NCEA) are grouped by Course Description
             */
            if (!isset($comments[$year]['courses'][$courseName])) {
                $comments[$year]['courses'][$courseName]['terms'] = self::INITIAL_TERMS;
                $comments[$year]['courses'][$courseName]['classCode'] = $classCode;
            }

            if (!isset($comments[$year]['classCodes'][$classCode])) {
                $comments[$year]['classCodes'][$classCode]['terms'] = self::INITIAL_TERMS;
                $comments[$year]['classCodes'][$classCode]['course'] = $courseName;
            }

            /* Compile data grouped by Course Description and also by Class Code
             * Different screens use different structures
             */
            $comments[$year]['courses'][$courseName]['terms'][$term] = $data;
            $comments[$year]['classCodes'][$classCode]['terms'][$term] = $data;
        }

        return $comments;
    }
}

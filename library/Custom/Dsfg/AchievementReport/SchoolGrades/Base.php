<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Dsfg\AchievementReport\SchoolGrades;

use Custom\Dsfg\AchievementReport\Base as AchievementReportBase;

abstract class Base extends AchievementReportBase
{

    /**
     * Rearrange into a meaningful structure
     *
     * @return array
     */
    protected function recombination(): array
    {

        /**
         * Rearrange everything into a nice order
         *
         * $yearStack is the table data
         */
        $yearStack = [];

        /* Entered Grades
         */
        $allEnteredGradesCurrentYear = $this->findEnteredGradesCurrentYear($this->getExternalUserId());
        $allEnteredGradesPreviousYear = $this->findEnteredGradesPreviousYear($this->getExternalUserId());

        /* Preprocess Current Year
         */
        foreach ($allEnteredGradesCurrentYear as $current) {
            $studentYear = $current['Fileyear'];
            $courseName = $current['CourseName'];
            $assessArea = $current['AssessAreaHeading'];
            $term = "term{$current['Term']}";

            /* Initialise
             */
            $yearStack[$studentYear][$courseName][$assessArea] ??= [
                'term1' => '',
                'term2' => '',
                'term3' => '',
                'term4' => '',
            ];

            /* Assign values
             */
            $yearStack[$studentYear][$courseName][$assessArea][$term] = $current;
        }

        /* Preprocess Previous Years
         */
        foreach ($allEnteredGradesPreviousYear as $current) {
            $studentYear = $current['Fileyear'];
            $courseName = $current['CourseName'];
            $assessArea = $current['AssessAreaHeading'];
            $term = "term{$current['Term']}";

            /* Initialise
             */
            $yearStack[$studentYear][$courseName][$assessArea] ??= [
                'term1' => '',
                'term2' => '',
                'term3' => '',
                'term4' => '',
            ];

            /* Assign values
             */
            $yearStack[$studentYear][$courseName][$assessArea][$term] = $current;
        }

        return $yearStack;
    }


    /**
     * Get counts for Entered Grades for the current year
     * for a particular student
     *
     * @param string $externalUserId
     * @return array
     */
    private function findEnteredGradesCurrentYear($externalUserId): array
    {
        $params = [
            'externalId' => $externalUserId,
            'year' => $this->getCurrentYear()
        ];

        $query = "
            SELECT
                classcode,
                StudentYearLevel,
                Fileyear,
                CourseName,
                AssessAreaHeading,
                Term,
                Result,
                StrippedResult
            FROM
                dbo.uvSchoolBoxSchoolGradeResults
            WHERE
                ID = :externalId
                AND classyear = :year
                AND Result != 'SNA'
            ORDER BY
                StudentYearLevel DESC,
                CourseName,
                AssessAreaHeading,
                Term DESC,
                StrippedResult DESC
        ";

        return $this->db->executeQuery($query, $params)->fetchAll();
    }


    /**
     * Get counts for Entered Grades for all previous years
     * for a particular student
     *
     * I'm not entirely sure why this needs to be separated but
     * whatever, I'm just going to follow the spreadsheet word for
     * word because who knows
     *
     * @param string $externalUserId
     * @return array
     */
    private function findEnteredGradesPreviousYear($externalUserId): array
    {
        $params = [
            'externalId' => $externalUserId,
            'year' => $this->getCurrentYear()
        ];

        $query = "
            SELECT
                classcode,
                StudentYearLevel,
                Fileyear,
                CourseName,
                AssessAreaHeading,
                Term,
                Result,
                StrippedResult
            FROM
                dbo.uvSchoolBoxSchoolGradeResults
            WHERE
                ID = :externalId
                AND classyear < :year
                AND Result != ''
                AND Result != 'SNA'
            ORDER BY
                StudentYearLevel DESC,
                CourseName,
                AssessAreaHeading,
                Term DESC,
                StrippedResult DESC
        ";

        return $this->db->executeQuery($query, $params)->fetchAll();
    }


    /**
     * Explorer, reporting!
     */
    protected function explorerReporting()
    {
        $explorer = $this->db->executeQuery("
            SELECT TOP 5 *
            FROM dbo.uvSchoolBoxSchoolGradeResults
        ")->fetchAll();

//        $explorer = $this->db->getAll("
//            SELECT
//                TABLE_CATALOG,
//                TABLE_SCHEMA,
//                TABLE_NAME,
//                TABLE_TYPE
//            FROM
//                INFORMATION_SCHEMA.TABLES
//            WHERE
//                TABLE_NAME LIKE '%uv%'
//        ");

        return $explorer;
    }
}

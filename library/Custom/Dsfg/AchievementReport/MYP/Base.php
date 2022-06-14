<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Dsfg\AchievementReport\MYP;

use Custom\Dsfg\AchievementReport\Base as AchievementReportBase;

/**
 * Class Base
 *
 * @package Custom\Dsfg\AchievementReport\MYP
 */
class Base extends AchievementReportBase
{
    /**
     * @inheritDoc
     */
    public function findData(): array
    {
        return $this->findMYPResults();
    }

    /**
     * Get MYP results by subjects for a particular student
     *
     * @return array
     */
    private function findMYPResults(): array
    {
        $params = [
            'externalId' => $this->getExternalUserId(),
            'year'       => $this->year,
        ];

        $query = "
            SELECT
                ID,
                ClassCampus,
                ClassCode,
                SubjectName,
                Overview,
                TopicHeading,
                ShortHeading,
                HdgAbbrev1,
                ResultNumeric,
                Comment,
                StaffMailNamePrimary,
                StaffEmail
            FROM
                uvSchoolBoxMYPResults
            WHERE
                SubjectName IS NOT NULL
                AND ID = :externalId
                AND FileYear = :year
        ";

        $rows     = $this->db->executeQuery($query, $params)->fetchAll();
        $mypData  = [];
        $headings = [];
        foreach ($rows as $row) {
            $subject      = trim($row['SubjectName']);
            $classCode    = trim($row['ClassCode']);
            $topicHeading = trim($row['TopicHeading']);
            $shortHeading = trim($row['ShortHeading']);
            $shortCode    = trim($row['HdgAbbrev1']);
            $staffName    = trim($row['StaffMailNamePrimary']);
            $staffEmail   = trim($row['StaffEmail']);

            // HEADINGS
            if (!key_exists($classCode, $headings)) {
                $headings[$classCode] = [
                    'subjectName'   => $subject,
                    'staffName'     => $staffName,
                    'staffEmail'    => $staffEmail,
                    'topicHeadings' => [],
                    'shortHeadings' => [],
                ];
            }
            if (!in_array($topicHeading, $headings[$classCode]['topicHeadings'])) {
                $headings[$classCode]['topicHeadings'][] = $topicHeading;
            }
            if (!in_array($shortHeading, $headings[$classCode]['shortHeadings'])) {
                $headings[$classCode]['shortHeadings'][$shortCode] = $shortHeading;
            }

            // DATA
            if (!key_exists($classCode, $mypData)) {
                $mypData[$classCode] = [
                    'topics'     => [],
                    'overview'   => $row['Overview'],
                    'comment'    => $row['Comment'],
                    'staffName'  => $staffName,
                    'staffEmail' => $staffEmail,
                ];
            }
            if (!key_exists($topicHeading, $mypData[$classCode]['topics'])) {
                $mypData[$classCode]['topics'][$topicHeading] = [];
            }
            $mypData[$classCode]['topics'][$topicHeading][$shortCode] =
                (int)$row['ResultNumeric'] === 0 ? '-' : (int)$row['ResultNumeric'];
        }

        foreach ($headings as $classCode => $subjectHeadings) {
            natsort($subjectHeadings['shortHeadings']);
            $headings[$classCode]['shortHeadings'] = $subjectHeadings['shortHeadings'];
            natsort($subjectHeadings['topicHeadings']);
            $headings[$classCode]['topicHeadings'] = $subjectHeadings['topicHeadings'];
        }

        return [
            'data'     => $mypData,
            'headings' => $headings,
        ];
    }

    /**
     * Get achievement level by subjects for a particular student
     * @return array
     */
    public function getMYPGrade(): array
    {
        $params = [
            'externalId' => $this->getExternalUserId(),
            'year'       => $this->year,
        ];

        $query = "
            SELECT
                AssessHeading,
                FileSemester,
                ClassCode,
                AssessAreaHdgAbbrev1,
                AssessAreaHdgAbbrev2,
                ID,
                AssessResultsResult,
                StaffMailName
            FROM uvSchoolBoxMYPGRADEResults
            WHERE StudentID = :externalId
            AND FileSemester IN (2, 4)
            AND FileYear = :year
        ";
        $rows  = $this->db->executeQuery($query, $params)->fetchAll();

        $gradeData = [];
        foreach ($rows as $row) {
            $gradeData[$row['ClassCode']][$row['FileSemester']][$row['AssessAreaHdgAbbrev2']] = $row;
        }
        return $gradeData;
    }
}

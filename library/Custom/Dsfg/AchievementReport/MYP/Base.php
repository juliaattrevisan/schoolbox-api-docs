<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Diocesan\AchievementReport\MYP;

use Custom\Diocesan\AchievementReport\Base as AchievementReportBase;

/**
 * Class Base
 *
 * @package Custom\Diocesan\AchievementReport\MYP
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
            'year'       => $this->getCurrentYear(),
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
                Comment
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
            $subject      = $row['SubjectName'];
            $classCode    = $row['ClassCode'];
            $topicHeading = $row['TopicHeading'];
            $shortHeading = $row['ShortHeading'];
            $shortCode    = $row['HdgAbbrev1'];

            // HEADINGS
            if (!key_exists($classCode, $headings)) {
                $headings[$classCode] = [
                    'subjectName'   => $subject,
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
                    'topics'   => [],
                    'overview' => $row['Overview'],
                    'comment'  => $row['Comment'],
                ];
            }
            if (!key_exists($topicHeading, $mypData[$classCode]['topics'])) {
                $mypData[$classCode]['topics'][$topicHeading] = [];
            }
            $mypData[$classCode]['topics'][$topicHeading][$shortCode] = (int)$row['ResultNumeric'];
        }

        foreach ($headings as $classCode => $subjectHeadings) {
            natsort($subjectHeadings['shortHeadings']);
            $headings[$classCode]['shortHeadings'] = $subjectHeadings['shortHeadings'];
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
        ];

        $query = "
            SELECT
                AssessHeading,
                FileSemester,
                ClassCode,
                AssessAreaHdgAbbrev1,
                AssessAreaHdgAbbrev2,
                ID,
                AssessResultsResult
            FROM uvSchoolBoxMYPGRADEResults
            WHERE StudentID = :externalId
            AND FileSemester IN (2, 4)
        ";
        $rows  = $this->db->executeQuery($query, $params)->fetchAll();

        $gradeData = [];
        foreach ($rows as $row) {
            $gradeData[$row['ClassCode']][$row['FileSemester']][$row['AssessAreaHdgAbbrev2']] = $row['AssessResultsResult'];
        }
        return $gradeData;
    }
}

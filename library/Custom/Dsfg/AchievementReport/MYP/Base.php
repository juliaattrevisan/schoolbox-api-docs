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
                ClassCode,
                SubjectName,
                Overview,
                TopicHeading,
                ShortHeading,
                HdgAbbrev1,
                ResultNumeric,
                StaffMailNamePrimary,
                StaffEmail
            FROM
                uvSchoolBoxMYPResults_CurrentOverview
            WHERE
                SubjectName IS NOT NULL
                AND ID = :externalId
                AND FileYear = :year
            ORDER BY SubjectName ASC, TopicHeading ASC
        ";

        $rows     = $this->db->executeQuery($query, $params)->fetchAll();
        $comments = $this->getComments();
        $mypData  = [];
        $headings = [];
        foreach ($rows as $row) {
            $subject      = trim((string)$row['SubjectName']);
            $classCode    = trim((string)$row['ClassCode']);
            $topicHeading = trim((string)$row['TopicHeading']);
            $shortHeading = trim((string)$row['ShortHeading']);
            $shortCode    = trim((string)$row['HdgAbbrev1']);
            $staffName    = trim((string)$row['StaffMailNamePrimary']);
            $staffEmail   = trim((string)$row['StaffEmail']);

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
                    'comments'   => $comments[$classCode] ?? [],
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
     * @return array
     */
    private function getComments(): array
    {
        $params = [
            'externalId' => $this->getExternalUserId(),
            'year'       => $this->year,
            'resultType' => 'MYPGRADE',
        ];

        $query = '
            SELECT
--                StudentYear,
                Term,
                ClassCode,
--                CourseDescription,
                Comment,
                ClassTeacher1,
                ClassTeacher1Email,
--                ClassTeacher2,
--                ClassTeacher2Email,
--                ClassTeacher3,
--                ClassTeacher3Email,
                ResultType
            FROM dbo.uvSchoolBoxReportComments
            WHERE id = :externalId
            AND StudentYear = :year
            AND ResultType = :resultType
            ORDER BY ClassCode, Term
        ';
        $rows  = $this->db->executeQuery($query, $params)->fetchAll();

        $commentData = [];
        foreach ($rows as $row) {
            $commentData[$row['ClassCode']]   ??= [];
            $commentData[$row['ClassCode']][] =
                [
                    'comment'    => sprintf('Term %s - %s', trim($row['Term']), trim($row['Comment'])),
                    'staffName'  => $row['ClassTeacher1'],
                    'staffEmail' => $row['ClassTeacher1Email'],
                ];
        }
        return $commentData;
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

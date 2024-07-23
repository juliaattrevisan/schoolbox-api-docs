<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Dsfg\AchievementReport\MYP;

use Custom\Dsfg\AchievementReport\Base as AchievementReportBase;
use External_User;

/**
 * Class Base
 *
 * @package Custom\Dsfg\AchievementReport\MYP
 */
class Base extends AchievementReportBase
{
    const END_OF_YEAR_SEMESTER_NUMBER = 4;

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
if ($_SESSION['loggedInUserIdForDebuggingPurposes'] ?? null === 1) {
    // Dump debugging info.
}

        $externalUserHandler = new External_User();
        $currentYear = date('Y');
        $currentSemester = $externalUserHandler->getCurrentSemester($this->getExternalUserId());

        $params = [
            'externalId' => $this->getExternalUserId(),
            'year'       => $this->year,
            'selectedFileSemester' => (
                ($this->year == $currentYear) ? self::END_OF_YEAR_SEMESTER_NUMBER: $currentSemester
            )
        ];
return [
    'data' => [
        'L&L:EL' => [
            'topics'     => [
                'A Wealth of Connections'      => ['A' => null, 'B' => null, 'C' => null, 'D' => null],
                'Books as Mirrors and Windows' => ['A' => null, 'B' => null, 'C' => null, 'D' => null],
                'Keep it Short, Sweetie'       => ['A' => 4   , 'B' => 5   , 'C' => null, 'D' => null],
                'Who is Billy Shakespeare'     => ['A' => null, 'B' => null, 'C' => 6   , 'D' => 4   ],
            ],
            'overview'   => 'This is the subject overview for L&L:EL.',
            'comments'   => [
                [
                    'comment' => 'Term 2 - Molly is a capable and focused student. She is producing work at a high level in both writing and speaking. Her essay last term presented ideas clearly and logically. Her writing was confident and mainly accurate. In the recent monologue assessment, Molly performed in-role as Kate, using language well and connecting to themes from the play. Her delivery was clear, though eye contact was limited, because she relied too heavily on cue cards and she spoke quickly. These are important aspects to remember before the next opportunity to present. Overall, she is having a positive year in English and it is a pleasure to teach her.',
                    'staffName' => 'Mr A Jones',
                    'staffEmail' => 'ajones@email.com',
                ]
            ],
            'staffName'  => 'Mr A Jones',
            'staffEmail' => 'ajones@email.com',
        ],
        'D:FDT' => [
        'topics'     => [
            'Chef Savvy'                   => ['A' => null, 'B' => 5   , 'C' => 7   , 'D' => null],
            'Day of the Dead'              => ['A' => null, 'B' => null, 'C' => null, 'D' => null],
            'Eat Street - Day of the Dead' => ['A' => 4   , 'B' => 5   , 'C' => null, 'D' => null],
        ],
        'overview'   => 'This is the subject overview for D:FDT.',
        'comments'   => [
            [
                'comment' => 'Term 2 - Molly works collaboratively in a group environment where she demonstrates increasing proficiency in her knife handling skills. She has continued to develop confidence as she accepts more complex technical challenges, thus becoming more intuitive in her practice. In the poke bowl assessment, she demonstrated her understanding of design principles and began to apply them to her concept drawings. Adding more detailed annotations would enhance her work. Molly is a pleasure to teach.',
                'staffName' => 'Miss H Peterson',
                'staffEmail' => 'hpeterson@email.com',
            ]
        ],
        'staffName'  => 'Miss H Peterson',
        'staffEmail' => 'ajones@email.com',
    ]
    ],
    'headings' => [
        'L&L:EL' => [
            'subjectName'   => 'Language and Literature: English Literature',
            'staffName'     => 'Mr A Jones',
            'staffEmail'    => 'ajones@email.com',
            'topicHeadings' => [
                'A Wealth of Connections',
                'Books as Mirrors and Windows',
                'Keep it Short, Sweetie',
                'Who is Billy Shakespeare',
            ],
            'shortHeadings' => [
                'A' => 'Analysing',
                'B' => 'Organising',
                'C' => 'Producing text',
                'D' => 'Using language'
            ],
        ],
        'D:FDT' => [
            'subjectName'   => 'Design: Food Design Technology',
            'staffName'     => 'Miss H Peterson',
            'staffEmail'    => 'hpeterson@email.com',
            'topicHeadings' => [
                'Chef Savvy',
                'Day of the Dead',
                'Eat Street - Day of the Dead'
            ],
            'shortHeadings' => [
                'A' => 'Inquiring and analysing',
                'B' => 'Developing ideas',
                'C' => 'Creating the solution',
                'D' => 'Evaluating'
            ]
        ]
    ]
];

// TODO: Get FileSemester in the query below, and use its value somehow to determine which Overview to display.
        // The DISTINCT keyword has been used because the view seems to be duplicating
        // rows because of an incorrect join involving the SubjectGrouping2 column.
        // Some rows have a SubjectGrouping2 value of NULL, and others have a value of
        // 'NULL' (a string).
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
                StaffEmail,
                ShortCourseFlag
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
            $subject       = trim((string)$row['SubjectName']);
            $classCode     = trim((string)$row['ClassCode']);
            $topicHeading  = trim((string)$row['TopicHeading']);
            $shortHeading  = trim((string)$row['ShortHeading']);
            $shortCode     = trim((string)$row['HdgAbbrev1']);
            $staffName     = trim((string)$row['StaffMailNamePrimary']);
            $staffEmail    = trim((string)$row['StaffEmail']);
            $isShortCourse = (trim((string)$row['ShortCourseFlag']) === '1');

            // HEADINGS
            if (!key_exists($classCode, $headings)) {
                $headings[$classCode] = [
                    'subjectName'   => $subject,
                    'staffName'     => $staffName,
                    'staffEmail'    => $staffEmail,
                    'topicHeadings' => [],
                    'shortHeadings' => [],
                    'isShortCourse' => $isShortCourse,
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
return [];

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

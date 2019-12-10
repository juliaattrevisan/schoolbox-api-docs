<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Diocesan\AchievementReport\SchoolGrades;

use Custom\Diocesan\AchievementReport\Comments\Comments;

/**
 * Class EntriesAndResults
 *
 * @package Custom\Diocesan\AchievementReport\SchoolGrades
 */
class EntriesAndResults extends Base
{

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Entries And Results';


    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $allEntries = $this->findEntries();

        /**
         * Rearrange everything into a nice order
         */
        $finalStack = [];

        foreach ($allEntries as $currentEntry) {
            $year = $currentEntry['Fileyear'];
            $courseName = $currentEntry['CourseName'];
            $assessArea = $currentEntry['AssessAreaHeading'];
            $term = $currentEntry['Term'];

            /* Strip all the numbers out
             */
            $result = preg_replace('/[0-9]*/', '', $currentEntry['Result']);

            /* List of teachers - uniquify and exclude empties
             */
            if (!is_array($finalStack[$year][$courseName][$assessArea]['teachers'])) {
                $finalStack[$year][$courseName]['allTeachers'] = [];
            }

            foreach([1, 2, 3] as $currentTeacherIndex) {
                $key = "ClassTeacher{$currentTeacherIndex}";
                $emailKey = "ClassTeacher{$currentTeacherIndex}Email";

                $currentTeacher = $currentEntry[$key];
                $currentTeacherEmail = $currentEntry[$emailKey];

                if (strlen($currentTeacher)) {
                    $finalStack[$year][$courseName]['allTeachers'][$currentTeacher] = $currentTeacherEmail;
                }
            }

            /* Initialise marks for All Terms
             */
            if (!is_array($finalStack[$year][$courseName]['allAreas'][$assessArea]['letters'])) {
                $finalStack[$year][$courseName]['allAreas'][$assessArea]['letters'] = [
                    'term1' => '',
                    'term2' => '',
                    'term3' => '',
                    'term4' => ''
                ];
            }

            /* Marks
             */
            $finalStack[$year][$courseName]['allAreas'][$assessArea]['letters']['term' . $term] = $result;
        }

        /* Sort naturally, because mssql can't
         */
        $processed = [];

        foreach ($finalStack as $year => $allCourses) {
            $processed[$year] = $allCourses;

            /* Get all the comments for this user and year
             */
            $comments = (new Comments($this->user, $this->externalUser, $this->db, $year))->findSchoolGradesData();

            foreach ($allCourses as $name => $data) {
                $subjects = $data['allAreas'];

                uksort($subjects, function($a, $b) {
                    preg_match('/^\d+\.(\d+)/', $a, $matchesA);
                    preg_match('/^\d+\.(\d+)/', $b, $matchesB);

                    return $matchesA[1] > $matchesB[1];
                });

                $processed[$year][$name] = $data;
                $processed[$year][$name]['allAreas'] = $subjects;
                $processed[$year][$name]['comments'] = $comments[$year]['courses'][$name]['terms'];
            }
        }

        return $processed;
    }


    /**
     * Find individual assessment areas and their marks per term
     *
     * Because term 2 and term 4 results may not always be the same,
     * even when finalised (see student 10086, "Learning From Religions")
     *
     * Spec suggests there is only one grade, but this doesn't seem
     * to be the case
     *
     * @return array
     */
    private function findEntries(): array
    {
        $params = [
            'externalId' => $this->getExternalUserId(),
        ];

        $query = "
            SELECT
                Fileyear,
                CourseName,
                AssessAreaHeading,
                Term,
                Result,
                ClassTeacher1,
                ClassTeacher1Email,
                ClassTeacher2,
                ClassTeacher2Email,
                ClassTeacher3,
                ClassTeacher3Email
            FROM
                dbo.uvSchoolBoxSchoolGradeResults
            WHERE
                ID = :externalId
                AND Result != 'SNA'
            GROUP BY
                FileYear,
                CourseName,
                AssessAreaHeading,
                Result,
                Term,
                AreaSeq,
                ClassTeacher1,
                ClassTeacher1Email,
                ClassTeacher2,
                ClassTeacher2Email,
                ClassTeacher3,
                ClassTeacher3Email
            ORDER BY
                Fileyear DESC,
                CourseName,
                AssessAreaHeading
        ";

        $result = $this->getDb()->getAll($query, $params);

        if (is_array($result)) {
            return $result;
        }

        return [];
    }
}

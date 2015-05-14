<?php
/**
 * EntriesAndResultsTable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\SchoolGrades;

class EntriesAndResults extends Base {

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Entries And Results';

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
    }

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
        $finalStack = array();

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
                $finalStack[$year][$courseName]['allTeachers'] = array();
            }

            foreach(array($currentEntry['ClassTeacher1'], $currentEntry['ClassTeacher2'], $currentEntry['ClassTeacher3']) as $currentTeacher) {
                if (strlen($currentTeacher) && !in_array($currentTeacher, $finalStack[$year][$courseName]['allTeachers'])) {
                    $finalStack[$year][$courseName]['allTeachers'][] = $currentTeacher;
                }
            }

            /* Initialise Term 2 and Term 4 marks
             */
            if (!is_array($finalStack[$year][$courseName][$assessArea]['letters'])) {
                $finalStack[$year][$courseName]['allAreas'][$assessArea]['letters'] = array(
                    'term2' => '',
                    'term4' => ''
                );
            }

            /* Term 2 and Term 4 marks
             */
            $finalStack[$year][$courseName]['allAreas'][$assessArea]['letters']['term' . $term] = $result;
        }

        return $finalStack;
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
    private function findEntries() {
        $query = "
            SELECT
                Fileyear,
                CourseName,
                AssessAreaHeading,
                Term,
                Result,
                AreaSeq,
                ClassTeacher1,
                ClassTeacher2,
                ClassTeacher3
            FROM
                SynergyOne.dbo.uvSchoolBoxSchoolGradeResults
            WHERE
                ID = '{$this->getExternalUserId()}'
            GROUP BY
                FileYear,
                CourseName,
                AssessAreaHeading,
                Result,
                Term,
                AreaSeq,
                ClassTeacher1,
                ClassTeacher2,
                ClassTeacher3
            ORDER BY
                Fileyear DESC,
                AreaSeq
        ";

        $result = $this->getDb()->getAll($query);

        if (is_array($result)) {
            return $result;
        }

        return array();
    }
}
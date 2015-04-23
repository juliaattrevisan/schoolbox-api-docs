<?php
/**
 * EntriesAndResults
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\NCEA;

use Alaress_Probe as Probe;

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
        $result = $this->findEntries();

        new Probe($this->explorerReporting(), $result);
    }

    /**
     * Find data about Entries and Results
     *
     * @return array
     */
    private function findEntries() {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        $query = "
            SELECT
                StudentYear,
                CourseDescription,
                ClassTeacher1,
                ClassTeacher2,
                ClassTeacher3,
                NCEAStandardNumber,
                InternalorExternalAssessment,
                StandardTitle,
                Level,
                EnteredCredits,
                CAST(CASE WHEN StudentYear = '{$this->getCurrentYear()}' AND InternalorExternalAssessment = 'EX' THEN 1 ELSE 0 END AS bit) AS ResultBrackets
            FROM
                SynergyOne.dbo.uvSchoolBoxNCEAResults
            WHERE
                id = '{$externalUserId}' AND
                WithdrawnFlag = 0
        ";

        $result = $this->getDb()->getAll($query);

        if (is_array($result)) {
            return $result;
        }

        return array();
    }
}
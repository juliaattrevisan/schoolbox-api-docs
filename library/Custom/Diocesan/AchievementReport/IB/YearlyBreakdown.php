<?php
/**
 * YearlyBreakdown
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Diocesan\AchievementReport\IB;

use Custom\Diocesan\AchievementReport\NormaliseTeachers;


class YearlyBreakdown extends Base
{

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $externalId = $this->getExternalUserId();

        $query = "
            SELECT *
            FROM dbo.uvSchoolBoxIBResultsNEW
            WHERE ID = '{$externalId}'
            AND studentibflag = 1
            AND fileyear = '{$this->year}'
            ORDER BY Term, Heading, AssessAreaSeq
        ";

        $results = $this->getDb()->getAll($query) ?: [];

        $finalStack = [];

        $teacherNormaliser = new NormaliseTeachers();

        foreach ($results as $current) {
            $courseName = $current['Heading'];
            $assessment = $current['AssessAreaHeading'];
            $result = $current['AssessResultsResult'];
            $term = $current['Term'];

            /* Assemble assessments if they have a title; otherwise disregard
             */
            if ($assessment) {
                $finalStack[$courseName]['assessments'][$assessment] = $result;
            }

            /* Teachers
             */
            $finalStack[$courseName]['teachers'] = $teacherNormaliser->normalise($current);

            /* Comments
             * I can assemble comments here without running it separately since I'm already
             * rebuilding this entire structure from the ground up
             */
            $finalStack[$courseName]['comments'][$term] = '';
        }

//        new \Alaress_Probe($results, $finalStack);

        return $finalStack;
    }
}
<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Diocesan\AchievementReport\IB;

use Custom\Diocesan\AchievementReport\NormaliseTeachers;

/**
 * Class YearlyBreakdown
 *
 * @package Custom\Diocesan\AchievementReport\IB
 */
class YearlyBreakdown extends Base
{

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $params = [
            'externalId' => $this->getExternalUserId(),
            'year' => $this->year
        ];

        $query = '
            SELECT *
            FROM dbo.uvSchoolBoxIBResultsNEW
            WHERE ID = :externalId
            AND studentibflag = 1
            AND fileyear = :year
            ORDER BY Heading, AssessAreaSeq, Term
        ';

        $results = $this->db->getAll($query, $params) ?: [];

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
            if (!isset($finalStack[$courseName]['comments'])) {
                $finalStack[$courseName]['comments'] = self::INITIAL_TERMS;
            }

            $finalStack[$courseName]['comments'][$term] = $current['assesscommentscomment'];
        }

        return $finalStack;
    }
}

<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Diocesan\AchievementReport\NCEA;

/**
 * Class CourseCreditSummaryNavigation
 *
 * Decide whether there is any data for Course Credit Summary
 * year-specific section
 *
 * @package Custom\Diocesan\AchievementReport\NCEA
 */
class CourseCreditSummaryNavigation extends Base
{

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Course Credit Summary';


    /**
     * Returns data for twig rendering
     *
     * Finds all the years that have data
     *
     * @return mixed
     */
    public function findData()
    {
        $params = [
            'externalId' => $this->getExternalUserId(),
            'year' => $this->getCurrentYear()
        ];

        $query = "
            SELECT
                StudentYear
            FROM
                dbo.uvSchoolBoxNCEAResults
            WHERE
                id = :externalId AND
                WithdrawnFlag = 0 AND
                NOT (StudentYear = :year AND InternalorExternalAssessment = 'EX')
            GROUP BY StudentYear
            ORDER BY StudentYear DESC
        ";

        $result = $this->db->getAll($query, $params);

        $allYears = [];

        if (is_array($result)) {
            foreach ($result as $current) {
                $allYears[] = $current['StudentYear'];
            }
        }

        return $allYears;
    }
}

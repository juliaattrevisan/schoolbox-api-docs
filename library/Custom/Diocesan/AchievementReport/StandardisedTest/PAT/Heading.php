<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\PAT;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;

/**
 * Class PATHeading
 *
 * Sadly we must inspect the db to find out which YearLevel corresponds
 * to the given calendar year for a particular student
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest
 */
class Heading extends Base
{

    /**
     * Find the Year Level for a student who was in a particular calendar year
     *
     * @return string
     */
    public function findData(): string
    {
        $params = [
            'externalId' => $this->getExternalUserId(),
            'year' => $this->year
        ];

        $query = '
            SELECT TOP 1 YearLevel
            FROM uvSchoolBoxPATSTARSummary
            WHERE ThisStudentID = :externalId
            AND Fileyear = :year
        ';

        $result = array_column($this->getDb()->getAll($query, $params), 'YearLevel');

        $yearLevel = array_pop($result);

        if (null !== $yearLevel) {
            return "Percentage of Year {$yearLevel} Children by Stanine";
        }

        return '';
    }
}

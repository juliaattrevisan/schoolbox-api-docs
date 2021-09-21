<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Dsfg\AchievementReport\StandardisedTest\PAT;

use Custom\Dsfg\AchievementReport\StandardisedTest\Base;

/**
 * Sadly we must inspect the db to find out which YearLevel corresponds
 * to the given calendar year for a particular student
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

        $result = array_column($this->db->executeQuery($query, $params)->fetchAll(), 'YearLevel');

        $yearLevel = array_pop($result);

        if (null !== $yearLevel) {
            return "Percentage of Year {$yearLevel} Children by Stanine";
        }

        return '';
    }
}

<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Dsfg\AchievementReport\StandardisedTest\STAR\Stanine;

use Custom\Dsfg\AchievementReport\StandardisedTest\Base;

/**
 * Get a list of years that have PAT Graph data
 */
class Navigation extends Base
{

    /**
     * Find all the years that have an Individual Stanine value != 0
     *
     * @return array
     */
    public function findData(): array
    {
        $params = [
            'externalId' => $this->getExternalUserId(),
        ];

        $query = '
            SELECT DISTINCT Fileyear
            FROM uvSchoolBoxPATSTARSummary
            WHERE ThisStudentID = :externalId
            AND IndividualStarTestStanineMarker <> 0
            ORDER BY Fileyear DESC
        ';

        return array_column($this->db->executeQuery($query, $params)->fetchAll(), 'Fileyear');
    }
}

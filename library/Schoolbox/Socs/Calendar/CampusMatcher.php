<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Calendar;

use Schoolbox\Campus;

class CampusMatcher
{
    /**
     * @param Campus[] $campuses
     * @param string[] $schoolNames
     * @return int[]
     */
    public function matchCampusIds(iterable $campuses, array $schoolNames): array
    {
        $campusIdsByName = [];
        foreach ($campuses as $campus) {
            $campusIdsByName[$campus->getName()] = (int)$campus->getId();
        }

        $matches = [];
        foreach ($schoolNames as $schoolName) {
            if (array_key_exists($schoolName, $campusIdsByName)) {
                $matches[] = $campusIdsByName[$schoolName];
            }
        }

        return array_values(array_unique($matches));
    }
}

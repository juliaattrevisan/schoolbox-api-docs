<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Dsfg\AchievementReport\StandardisedTest\PAT\Mathematics;

use Schoolbox\YearLevel;

/**
 * Determine whether the PAT Mathematics graph should appear
 */
class ValidHasData
{

    /**
     * Certain year levels always display the graph.
     * Other year levels display the graph only if there is data there
     *
     * @param YearLevel\Model|null $yearLevel
     * @param bool                 $hasData
     *
     * @return bool
     */
    public function isSatisfiedBy(?YearLevel\Model $yearLevel, bool $hasData): bool
    {
        return $yearLevel && \in_array((int)$yearLevel->getName(), [3,4,5,6,7,8,9,10], true) && $hasData;
    }
}

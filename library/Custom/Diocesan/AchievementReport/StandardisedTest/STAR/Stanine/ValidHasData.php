<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\STAR\Stanine;

use Schoolbox\YearLevel;

/**
 * Class ValidHasData
 *
 * Determine whether the STAR Stanine graph should appear
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\STAR\Stanine
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
        if ($hasData) {
            return true;
        }

        if ($yearLevel && 3 === (int)$yearLevel->getName()) {
            return true;
        }

        return false;
    }
}

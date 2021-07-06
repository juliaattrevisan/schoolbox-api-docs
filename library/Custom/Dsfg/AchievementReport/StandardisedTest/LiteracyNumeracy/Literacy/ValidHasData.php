<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Dsfg\AchievementReport\StandardisedTest\LiteracyNumeracy\Literacy;

use Schoolbox\YearLevel;

/**
 * Class ValidHasData
 *
 * Determine whether the Writing graph should appear
 *
 * @package Custom\Dsfg\AchievementReport\StandardisedTest\LiteracyNumeracy\Literacy
 */
class ValidHasData
{

    /**
     * Certain year levels always display the graph.
     * Other year levels display the graph only if there is data there
     *
     * @param YearLevel\Model|null $yearLevel
     * @param bool                            $hasData
     *
     * @return bool
     */
    public function isSatisfiedBy(?YearLevel\Model $yearLevel, bool $hasData): bool
    {
        if ($hasData) {
            return true;
        }

        if ($yearLevel && \in_array((int)$yearLevel->getName(), [7,8], true)) {
            return true;
        }

        return false;
    }
}

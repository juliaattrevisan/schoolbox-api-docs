<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\PAT\ReadingComprehension;

use Schoolbox\YearLevel;

/**
 * Class ValidHasData
 *
 * Determine whether the PAT Reading Comprehension graph should appear
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest
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
        return $yearLevel && \in_array((int)$yearLevel->getName(), [4,5,6,7,8,9,10], true) && $hasData;
    }
}

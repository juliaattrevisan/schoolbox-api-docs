<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\PAT\Table;

/**
 * Class ValidHasData
 *
 * Determine whether the PAT Table should appear
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\PAT\Table
 */
class ValidHasData
{

    /**
     * Certain year levels always display the table.
     * Other year levels display the table only if there is data there
     *
     * @param int  $yearLevel
     * @param bool $hasData
     *
     * @return bool
     */
    public function isSatisfiedBy(int $yearLevel, bool $hasData): bool
    {
        return \in_array($yearLevel, [7,8,9,10], true) || $hasData;
    }
}

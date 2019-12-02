<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest;

/**
 * Class ValidListeningComprehension
 *
 * Determine whether the PAT Listening Comprehension graph should appear
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest
 */
class ValidPATListeningComprehension
{

    /**
     * Certain year levels always display the graph.
     * Other year levels display the graph only if there is data there
     *
     * @param int  $yearLevel
     * @param bool $hasData
     *
     * @return bool
     */
    public function isSatisfiedBy(int $yearLevel, bool $hasData): bool
    {
        return \in_array($yearLevel, [3,4,5,6,7,8,9,10], true) || $hasData;
    }
}

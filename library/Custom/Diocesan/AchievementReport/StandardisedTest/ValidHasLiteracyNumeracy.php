<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest;

/**
 * Class ValidHasLiteracyNumeracy
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest
 */
class ValidHasLiteracyNumeracy
{

    /**
     * Certain year levels always display the graph.
     * Other year levels display the graph only if there is data there
     *
     * @param int   $yearLevel
     * @return bool
     */
    public function isSatisfiedBy(int $yearLevel): bool
    {
        /* "Can you please hide the Literacy/Numeracy tab for all students in current years 0-6?"
         * https://lrs.zendesk.com/agent/tickets/76478
         */
        return !\in_array($yearLevel, [0, 1, 2, 3, 4, 5, 6], true);
    }
}

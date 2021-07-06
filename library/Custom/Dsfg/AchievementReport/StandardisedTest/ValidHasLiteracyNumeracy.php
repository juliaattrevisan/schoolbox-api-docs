<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Dsfg\AchievementReport\StandardisedTest;

use Schoolbox\YearLevel;

/**
 * Class ValidHasLiteracyNumeracy
 *
 * @package Custom\Dsfg\AchievementReport\StandardisedTest
 */
class ValidHasLiteracyNumeracy
{

    /**
     * Certain year levels always display the graph.
     * Other year levels display the graph only if there is data there
     *
     * @param YearLevel\Model|null $yearLevel
     * @return bool
     */
    public function isSatisfiedBy(?YearLevel\Model $yearLevel): bool
    {

        /* "Can you please hide the Literacy/Numeracy tab for all students in current years 0-6?"
         * https://lrs.zendesk.com/agent/tickets/76478
         */
        return $yearLevel && !\in_array($yearLevel->getName(), [0, 1, 2, 3, 4, 5, 6], true);
    }
}

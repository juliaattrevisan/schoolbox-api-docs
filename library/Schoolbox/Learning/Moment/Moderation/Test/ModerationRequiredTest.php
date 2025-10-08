<?php

/**
 * ModerationRequiredTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Moderation\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Moment\Moderation\ModerationRequired;
use Schoolbox\YearLevel\Model;

/**
 * ModerationRequiredTest
 */
class ModerationRequiredTest extends TestCase
{
    /**
     * @param bool $globalConfig
     * @param Model|null $yearLevel
     * @param int $expected
     */
    #[DataProvider('providerModerationRequired')]
    public function testModerationRequired(bool $globalConfig, ?\Schoolbox\YearLevel\Model $yearLevel, $expected)
    {
        $outcome = (new ModerationRequired())->isSatisfiedBy($globalConfig, $yearLevel);

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testModerationRequired
     *
     * "The moderation ON state is defined as:
     * Learning moment moderation is enabled for students year level
     * Or if student does not have year level, global learning moderation config is enabled"
     *
     * @return array
     */
    public static function providerModerationRequired(): array
    {
        $yearLevelEnabled = (new \Schoolbox\YearLevel\Model())
            ->setLearningMomentsModeration(true);

        $yearLevelDisabled = (new \Schoolbox\YearLevel\Model())
            ->setLearningMomentsModeration(false);

        return [
            'Global config enabled, Year level config disabled' => [
                true,
                $yearLevelDisabled,
                false
            ],

            'Global config disabled, Year Level config enabled' => [
                false,
                $yearLevelEnabled,
                true
            ],

            'Global config and year level config both enabled' => [
                true,
                $yearLevelEnabled,
                true
            ],

            'Global config and year level config both disabled' => [
                false,
                $yearLevelDisabled,
                false
            ],

            /* When the user doesn't have a year level, rely on the global config
             */
            'Global config enabled, null year level' => [
                true,
                null,
                true
            ],

            'Global config disabled, null year level' => [
                false,
                null,
                false
            ]
        ];
    }
}

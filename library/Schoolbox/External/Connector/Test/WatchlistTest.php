<?php

/**
 * WatchlistTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\External\Connector\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Watchlist;

/**
 * Class WatchlistTest
 *
 * Find meaningful values from a csv set. These are used to filter
 * data in the suds importers to specific individuals
 *
 * @package Schoolbox\External\Connector\Test
 */
class WatchlistTest extends TestCase
{
    /**
     * @param string $externalId
     * @param string $cliArgs
     * @param bool   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidWatchlistValue')]
    public function testValidWatchlistValue(string $externalId, string $cliArgs, bool $expected)
    {
        $watchlist = new Watchlist();

        $externalIds = $watchlist->filter($cliArgs);

        $this->assertEquals($expected, (new Watchlist())->isOnWatchlist($externalId, $externalIds));
    }


    /**
     * Data provider for testValidWatchlistValue
     *
     * @return array
     */
    public static function providerValidWatchlistValue(): array
    {
        return [
            /* Empty watchlist allows everyone (this is the default usage)
             */
            'Default Usage Example 1' => [
                '',
                '',
                true
            ],

            'Default Usage Example 2' => [
                '12345',
                '',
                true
            ],

            /* A specific externalId needs to be among the csv
             */
            'Specific Example 1' => [
                '12345',
                '1,2,3,4,5,6,7,8,9,0',
                false
            ],

            'Specific Example 2' => [
                '7',
                '1,2,3,4,5,6,7,8,9,0',
                true
            ],

            /* It's not limited to alphnums and you can malform the csv
             * to some extent - since we do it via cli, accommodate most
             * common typo/laziness
             */
            'Varied Example 1' => [
                '3c_cats',
                '1a, 2b-12,      3c_cats   ,      8d',
                true
            ],
        ];
    }
}

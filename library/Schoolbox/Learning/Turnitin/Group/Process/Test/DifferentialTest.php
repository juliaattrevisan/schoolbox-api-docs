<?php

/**
 * DifferentialTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Group\Process\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Turnitin\Group\Process\Differential;

class DifferentialTest extends TestCase
{
    /**
     * @param array $localIds
     * @param array $remoteIds
     * @param array $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidDifferential')]
    public function testValidDifferential(array $localIds, array $remoteIds, array $expected)
    {
        $this->assertEquals($expected, (new Differential())->generate($localIds, $remoteIds));
    }

    /**
     * Data provider for testValidDifferential
     *
     * @return array
     */
    public static function providerValidDifferential(): array
    {
        return [
            /* Some local data exists in remote data
             * Some remote data no longer exists in local data
             */
            'Overlaps' => [
                ['cat', 'lion', 'puma', 'panther', 'ocelot' ],
                ['cat', 'horse', 'elephant', 'ocelot'],
                [
                    'delete' => ['horse', 'elephant'],
                    'keep' => ['cat', 'ocelot'],
                    'add' => ['lion', 'puma', 'panther']
                ]
            ],

            /* Remove everything from remote data
             */
            'Remotes only' => [
                [],
                ['cat', 'horse', 'elephant', 'ocelot'],
                [
                    'delete' => ['cat', 'horse', 'elephant', 'ocelot'],
                    'keep' => [],
                    'add' => []
                ]
            ],

            /* First adds from local to pristine remote data
             */
            'Locals only' => [
                ['cat', 'horse', 'elephant', 'ocelot'],
                [],
                [
                    'delete' => [],
                    'keep' => [],
                    'add' => ['cat', 'horse', 'elephant', 'ocelot'],
                ]
            ],

            /* Local data completely different to remote data;
             * replace the entire remote data
             */
            'No intersect' => [
                ['cat', 'lion', 'puma', 'panther', 'ocelot' ],
                ['horse', 'elephant', 'rectangle'],
                [
                    'delete' => ['horse', 'elephant', 'rectangle'],
                    'keep' => [],
                    'add' => ['cat', 'lion', 'puma', 'panther', 'ocelot' ],
                ]
            ],

            /* Local data is the same as remote data
             * No change required
             */
            'Full intersect' => [
                ['cat', 'lion', 'puma', 'panther', 'ocelot' ],
                ['cat', 'lion', 'puma', 'panther', 'ocelot' ],
                [
                    'delete' => [],
                    'keep' => ['cat', 'lion', 'puma', 'panther', 'ocelot' ],
                    'add' => [],
                ]
            ],

            /* Pristine conditions with nothing to do
             */
            'All empty' => [
                [],
                [],
                [
                    'delete' => [],
                    'keep' => [],
                    'add' => [],
                ]
            ],
        ];
    }
}

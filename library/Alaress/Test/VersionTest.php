<?php

/**
 * VersionTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Alaress\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Version test
 */
class VersionTest extends TestCase
{
    /**
     * @param mixed $input
     * @param int   $expected
     */
    #[DataProvider('providerValidVersions')]
    public function testValidVersion($input, $expected)
    {
        $version = new \Alaress\Application\Version();

        $this->assertEquals($expected, $version->generate($input));
    }

    /**
     * Data provider for testValidVersion
     *
     * @return array
     */
    public static function providerValidVersions(): array
    {
        return [
            'Valid Version 1' => [
                '24.2.alpha1',
                '24.2'
            ],

            'Valid Version 2' => [
                '24.2.23',
                '24.2'
            ],

            'Valid Version 3' => [
                '26.1.beta23',
                '26.1'
            ]
        ];
    }


    /**
     * You should also test for error states that your code may raise.
     *
     * @dataProvider getTestErrorRaisedData
     *
     */
    #[DataProvider('getTestErrorRaisedData')]
    public function testErrorRaised($input): void
    {
        $this->expectException(\DomainException::class);

        // Substitute this with the method call that is supposed to fail.

        (new \Alaress\Application\Version())->generate($input);
    }


    /**
     * Data provider for `testErrorRaised`.
     *
     * @return array
     */
    public static function getTestErrorRaisedData(): array
    {
        return [
            ['26.0'],
            ['26.1.2.3.4.5'],
            ['26'],
            ['26..................'],
            [''],
            ['  '],
            ['                              26'],
            ['                              26      .9  '],
            [null],
        ];
    }
}

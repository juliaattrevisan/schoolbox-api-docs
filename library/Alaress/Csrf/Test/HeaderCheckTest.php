<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Csrf\Test;

use Alaress\Csrf\HeaderCheck;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSRF header checks.
 */
class HeaderCheckTest extends TestCase
{
    /**
     * Data provider for `testHeadersOnly`.
     *
     * @return array
     */
    public static function providerForTestHeadersOnly()
    {
        return [
            'validOriginAndHost' => [
                'serverVars' => [
                    'HTTP_ORIGIN' => 'http://192.168.36.10/adminv2/menu-top',
                    'HTTP_HOST'   => '192.168.36.10',
                ],
                'expectedResult' => true,
            ],
            'validRefererAndHost' => [
                'serverVars' => [
                    'HTTP_REFERER' => 'http://192.168.36.10/adminv2/menu-top',
                    'HTTP_HOST'    => '192.168.36.10',
                ],
                'expectedResult' => true,
            ],
            'validOriginAndForwardedHost' => [
                'serverVars' => [
                    'HTTP_ORIGIN'           => 'http://192.168.36.10/adminv2/menu-top',
                    'HTTP_X_FORWARDED_HOST' => '192.168.36.10',
                ],
                'expectedResult' => true,
            ],
            'validRefererAndForwardedHost' => [
                'serverVars' => [
                    'HTTP_REFERER'          => 'http://192.168.36.10/adminv2/menu-top',
                    'HTTP_X_FORWARDED_HOST' => '192.168.36.10',
                ],
                'expectedResult' => true,
            ],
            'preferMatchingOriginToReferer' => [
                'serverVars' => [
                    'HTTP_ORIGIN'  => 'http://192.168.36.10/adminv2/menu-top',
                    'HTTP_REFERER' => 'http://google.com',
                    'HTTP_HOST'    => '192.168.36.10',
                ],
                'expectedResult' => true,
            ],
            'preferForwardedHostToProxyHost' => [
                'serverVars' => [
                    'HTTP_ORIGIN'           => 'http://192.168.36.10/adminv2/menu-top',
                    'HTTP_HOST'             => '192.168.36.10',
                    'HTTP_X_FORWARDED_HOST' => 'google.com',
                ],
                'expectedResult' => false,
            ],
            'preferNonMatchingOriginToReferer' => [
                'serverVars' => [
                    'HTTP_ORIGIN'  => 'http://google.com',
                    'HTTP_REFERER' => 'http://192.168.36.10/adminv2/menu-top',
                    'HTTP_HOST'    => '192.168.36.10',
                ],
                'expectedResult' => false,
            ],
            'preferForwardedHostToNonMatchingProxyHost' => [
                'serverVars' => [
                    'HTTP_ORIGIN'           => 'http://192.168.36.10/adminv2/menu-top',
                    'HTTP_HOST'             => 'google.com',
                    'HTTP_X_FORWARDED_HOST' => '192.168.36.10',
                ],
                'expectedResult' => true,
            ],
            'noSource' => [
                'serverVars' => [
                    'HTTP_HOST' => '192.168.36.10',
                ],
                'expectedResult' => true,
            ],
            'noTarget' => [
                'serverVars' => [
                    'HTTP_ORIGIN' => 'http://192.168.36.10/adminv2/menu-top',
                ],
                'expectedResult' => false,
            ],
            'noSourceOrTarget' => [
                'serverVars' => [],
                'expectedResult' => true,
            ],
            'malformedOrigin' => [
                'serverVars' => [
                    'HTTP_ORIGIN' => 'file://',
                    'HTTP_HOST'   => '192.168.36.10',
                ],
                'expectedResult' => false,
            ],
            'nullOrigin' => [
                'serverVars' => [
                    'HTTP_ORIGIN' => 'null',
                    'HTTP_HOST'   => '192.168.36.10',
                ],
                'expectedResult' => true,
            ],
            'emptyOrigin' => [
                'serverVars' => [
                    'HTTP_ORIGIN' => '',
                    'HTTP_HOST'   => '192.168.36.10',
                ],
                'expectedResult' => true,
            ],
            'malformedOriginAndHost' => [
                'serverVars' => [
                    'HTTP_ORIGIN' => 'file://',
                    'HTTP_HOST'   => 'file://',
                ],
                'expectedResult' => false,
            ],
        ];
    }

    /**
     *
     * @param array $serverVars
     * @param bool  $expectedResult
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestHeadersOnly')]
    public function testHeadersOnly(array $serverVars, bool $expectedResult)
    {
        $check = new HeaderCheck($serverVars);
        $this->assertEquals($expectedResult, $check->verifyRequestOrigin());
    }

    /**
     * Data provider for `testHeadersAndValidHost`.
     *
     * @return array
     */
    public static function providerForHeadersAndValidHost()
    {
        return [
            'validOrigin' => [
                'serverVars' => [
                    'HTTP_ORIGIN' => 'http://192.168.36.10/adminv2/menu-top',
                ],
                'validHost' => '192.168.36.10',
                'expectedResult' => true,
            ],
            'validReferer' => [
                'serverVars' => [
                    'HTTP_REFERER' => 'http://192.168.36.10/adminv2/menu-top',
                ],
                'validHost' => '192.168.36.10',
                'expectedResult' => true,
            ],
            'invalidOrigin' => [
                'serverVars' => [
                    'HTTP_ORIGIN' => 'http://google.com',
                ],
                'validHost' => '192.168.36.10',
                'expectedResult' => false,
            ],
            'preferValidHostToHeaderHost' => [
                'serverVars' => [
                    'HTTP_ORIGIN'           => 'http://192.168.36.10/adminv2/menu-top',
                    'HTTP_HOST'             => 'google.com',
                ],
                'validHost' => '192.168.36.10',
                'expectedResult' => true,
            ],
            'invalidReferer' => [
                'serverVars' => [
                    'HTTP_REFERER' => 'http://google.com',
                ],
                'validHost' => '192.168.36.10',
                'expectedResult' => false,
            ],
            'noSource' => [
                'serverVars' => [],
                'validHost' => '192.168.36.10',
                'expectedResult' => true,
            ],
        ];
    }

    /**
     *
     * @param array       $serverVars
     * @param null|string $validHost
     * @param bool        $expectedResult
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForHeadersAndValidHost')]
    public function testHeadersAndValidHost(array $serverVars, ?string $validHost, bool $expectedResult)
    {
        $check = new HeaderCheck($serverVars, $validHost);
        $this->assertEquals($expectedResult, $check->verifyRequestOrigin());
    }
}

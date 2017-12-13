<?php
/**
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Csrf\Test;

use Alaress\Csrf\HeaderCheck;
use PHPUnit_Framework_TestCase;

/**
 * Tests for headerchecks
 */
class HeaderCheckTest extends PHPUnit_Framework_TestCase
{
    public function providerForTestHeadersOnly()
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
            'preferMatchingHostToForwardedHost' => [
                'serverVars' => [
                    'HTTP_ORIGIN'           => 'http://192.168.36.10/adminv2/menu-top',
                    'HTTP_HOST'             => '192.168.36.10',
                    'HTTP_X_FORWARDED_HOST' => 'google.com',
                ],
                'expectedResult' => true,
            ],
            'preferNonMatchingOriginToReferer' => [
                'serverVars' => [
                    'HTTP_ORIGIN'  => 'http://google.com',
                    'HTTP_REFERER' => 'http://192.168.36.10/adminv2/menu-top',
                    'HTTP_HOST'    => '192.168.36.10',
                ],
                'expectedResult' => false,
            ],
            'preferNonMatchingHostToForwardedHost' => [
                'serverVars' => [
                    'HTTP_ORIGIN'           => 'http://192.168.36.10/adminv2/menu-top',
                    'HTTP_HOST'             => 'google.com',
                    'HTTP_X_FORWARDED_HOST' => '192.168.36.10',
                ],
                'expectedResult' => false,
            ],
            'noSource' => [
                'serverVars' => [
                    'HTTP_HOST' => '192.168.36.10',
                ],
                'expectedResult' => false,
            ],
            'noTarget' => [
                'serverVars' => [
                    'HTTP_ORIGIN' => 'http://192.168.36.10/adminv2/menu-top',
                ],
                'expectedResult' => false,
            ],
            'noSourceOrTarget' => [
                'serverVars' => [],
                'expectedResult' => false,
            ],
        ];
    }

    /**
     * @dataProvider providerForTestHeadersOnly
     *
     * @param array $serverVars
     * @param bool  $expectedResult
     */
    public function testHeadersOnly(array $serverVars, bool $expectedResult)
    {
        $check = new HeaderCheck($serverVars);
        $this->assertEquals($expectedResult, $check->check());
    }
}

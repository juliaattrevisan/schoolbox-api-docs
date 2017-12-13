<?php
/**
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Csrf\Test;

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
            'preferOriginToReferer' => [
                'serverVars' => [
                    'HTTP_ORIGIN'  => 'http://192.168.36.10/adminv2/menu-top',
                    'HTTP_REFERER' => 'http://google.com',
                    'HTTP_HOST'    => '192.168.36.10',
                ],
                'expectedResult' => true,
            ],
            'preferHostToForwardedHost' => [
                'serverVars' => [
                    'HTTP_ORIGIN'           => 'http://192.168.36.10/adminv2/menu-top',
                    'HTTP_HOST'             => '192.168.36.10',
                    'HTTP_X_FORWARDED_HOST' => 'google.com',
                ],
                'expectedResult' => true,
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

    }
}

<?php
/**
 * ValidOAuth
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Twitter\Loader\Test;

use PHPUnit\Framework\TestCase;
use Alaress\Twitter\Loader\ValidOAuth;


/**
 * Class ValidationTest
 * @package Schoolbox\ProfessionalDevelopment\Test
 */
class ValidOAuthTest extends TestCase
{

    /**
     * @dataProvider provider_validOAuth
     * @param     $consumerKey
     * @param     $consumerSecret
     * @param     $oauthToken
     * @param     $oauthSecret
     * @param int $expected
     */
    public function testValidOauth(
        $consumerKey,
        $consumerSecret,
        $oauthToken,
        $oauthSecret,
        $expected
    ): void {
        $validation = new ValidOAuth();

        $this->assertEquals($expected, $validation->isSatisfiedBy(
            $consumerKey, $consumerSecret, $oauthToken, $oauthSecret
        ));
    }

    /**
     * Data provider for testValidOauth
     *
     * @return array
     */
    public function provider_validOAuth() :array {
        return [
            'Valid Complete Details 1' => [
                'a123aa123', 'b123bb', 'cc52345c', 'dd472d',
                true
            ],
            'Invalid Complete Details 1' => [
                null, null, null, null,
                false
            ],
            'Invalid Complete Details 2' => [
                '', '', '', '',
                false
            ],
            'Invalid Complete Details 3' => [
                '  ', '    ', ' ', '     ',
                false
            ],
            'Invalid Complete Details 4' => [
                ' 34  67f', '    ', 'h 3463', '     ',
                false
            ],
            'Invalid Complete Details 5' => [
                '  ', 'a s5   ', ' ', ' d    ',
                false
            ],
            'Invalid Complete Details 6' => [
                ' a ', ' S FJ(!@$ ', 'SDFJKL! ', '     ',
                false
            ],
            'Invalid Complete Details 7' => [
                ' aAJ ($%jlh ', ' b  234251sdf!!$ ', 'c ', null,
                false
            ],
        ];
    }
}

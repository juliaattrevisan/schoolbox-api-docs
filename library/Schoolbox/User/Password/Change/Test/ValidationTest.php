<?php

/**
 * ValidationTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\User\Password\Change\Test;

use Alaress\Password\Service;
use PHPUnit\Framework\TestCase;
use Schoolbox\User\Password\Change\ValidChangeRequest;

class ValidationTest extends TestCase
{
    /**
     * @param string $currentPassword
     * @param string $userPassword
     * @param string $newPassword
     * @param string $confirmPassword
     * @param bool   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validChangeRequest')]
    public function testValidChangeRequest(string $currentPassword, string $userPassword, string $newPassword, string $confirmPassword, bool $expected)
    {
        /* Substitute this with a spec pattern or other conditional
         */
        $service = new Service();

        $outcome = (new ValidChangeRequest($service))->isSatisfiedBy($currentPassword, $service->hash($userPassword), $newPassword, $confirmPassword);

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidChangeRequest
     *
     * @return array
     */
    public static function provider_validChangeRequest(): array
    {
        return [

            /* Add other cases that you expect to be valid
             */
            'Valid 1' => [
                'password',
                'password',
                'catcatcat',
                'catcatcat',
                true
            ],

            'Valid 2' => [
                'somethingelse',
                'somethingelse',
                'catcatcat',
                'catcatcat',
                true
            ],

            /* current password doesn't match the one in the user table
             */
            'Invalid A1' => [
                'password',
                'somethingelse',
                'catcatcat',
                'catcatcat',
                false
            ],

            'Invalid A2' => [
                '',
                'somethingelse',
                'catcatcat',
                'catcatcat',
                false
            ],

            /* minimum length not met
             */
            'Invalid B1' => [
                'password',
                'password',
                'catcatc',
                'catcatc',
                false
            ],

            'Invalid B2' => [
                'password',
                'password',
                'cat',
                'cat',
                false
            ],

            'Invalid B3' => [
                'password',
                'password',
                '',
                '',
                false
            ],

            /* new password doesn't match confirm password
             */
            'Invalid C1' => [
                'password',
                'password',
                'catcatcat',
                'dogdogdog',
                false
            ],

            /* Some whacked out combination that shouldn't exist
             */
            'Invalid Z1' => [
                '',
                'q',
                '',
                'e',
                false
            ],
        ];
    }
}

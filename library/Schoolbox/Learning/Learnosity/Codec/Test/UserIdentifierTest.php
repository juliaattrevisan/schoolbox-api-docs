<?php

/**
 * UserIdentifierTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Codec\Test;

use Schoolbox\Learning\Learnosity\Codec\UserIdentifier;
use PHPUnit\Framework\TestCase;

class UserIdentifierTest extends TestCase
{
    /**
     * @param mixed $instanceId
     * @param mixed $userId
     * @param int   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidUserIdentifier')]
    public function testValidUserIdentifier($instanceId, $userId, $expected)
    {
        $outcome = (new UserIdentifier())->generate($instanceId, $userId);

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidExample
     *
     * @return array
     */
    public static function providerValidUserIdentifier(): array
    {
        return [
            'Valid 1' => [
                'vagrantx',
                '123',
                'vagrantx_123'
            ],

            'Valid 2' => [
                'vagrantx',
                123,
                'vagrantx_123'
            ],
        ];
    }
}

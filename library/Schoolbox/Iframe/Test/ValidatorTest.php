<?php

/**
 * ValidatorTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Iframe\Test;

use Schoolbox\Iframe\Validator;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    /**
     * @param $safeSources
     * @param $url
     * @param int $expected
     * @throws ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidExample')]
    public function testValidExample($safeSources, $url, $expected)
    {
        $outcome = (new Validator($safeSources))->isValid($url);

        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidExample
     *
     * @return array
     */
    public static function providerValidExample(): array
    {
        $safeSources = "
            soundcloud.com/
            w.soundcloud.com
            api.soundcloud.com
            schoolbox.education
        ";

        return [
            'Valid 1: url is in safeSources list' => [
                $safeSources,
                'schoolbox.education/test.html',
                true
            ],

            'Valid 2: url is in safeSources list' => [
                $safeSources,
                'schoolbox.education/cat/horse/elephant/test.html',
                true
            ],

            /* Add other cases that you expect to be invalid
             */
            'Invalid 1: empty safeSources list' => [
                '',
                'google.com/test.html',
                false
            ],

            'Invalid 2: url not in safeSources list' => [
                $safeSources,
                'google.com/test.html',
                false
            ],

            'Invalid 3: url not in safeSources list' => [
                $safeSources,
                '',
                false
            ],
        ];
    }
}

<?php

/**
 * ValidationTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Comms\News\Statistics;

class ValidationTest extends TestCase
{

    /**
     * @param null|string $referrer
     * @param bool        $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validOpenReferrer')]
    public function testValidOpenReferrer(?string $referrer, bool $expected)
    {
        $validation = new Statistics\ValidOpenReferrer();

        $this->assertEquals($expected, $validation->isSatisfiedBy($referrer));
    }

    /**
     * Data provider for testValidOpenReferrer
     *
     * @return array
     */
    public static function provider_validOpenReferrer(): array
    {
        return [
            'Valid referrer: dashboard' => [
                Statistics\Mapper::REFERRER_DASHBOARD,
                true
            ],
            'Valid referrer: homepage' => [
                Statistics\Mapper::REFERRER_HOMEPAGE,
                true
            ],
            'Valid referrer: index' => [
                Statistics\Mapper::REFERRER_INDEX,
                true
            ],
            'Valid referrer: archive' => [
                Statistics\Mapper::REFERRER_ARCHIVE,
                true
            ],
            'Valid referrer: email' => [
                Statistics\Mapper::REFERRER_EMAIL,
                true
            ],
            'Valid referrer: saved' => [
                Statistics\Mapper::REFERRER_SAVED,
                true
            ],
            'Valid referrer: search' => [
                Statistics\Mapper::REFERRER_SEARCH,
                true
            ],
            'Valid referrer: null' => [
                null,
                true
            ],
            'Invalid referrer: empty string' => [
                '',
                false
            ],
            'Invalid referrer: just whitespaces' => [
                '         ',
                false
            ],
            'Invalid referrer: unrecognized string 1' => [
                'the ultimate cat',
                false
            ],

            'Invalid referrer: unrecognized string 2' => [
                ' (@$&*( 012 40 "',
                false
            ],

            'Invalid referrer: unrecognized string 3' => [
                'email dashboard homepage archive',
                false
            ],
        ];
    }
}

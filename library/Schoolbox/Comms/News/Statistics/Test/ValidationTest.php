<?php
/**
 * ValidationTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Comms\News\Statistics;

class ValidationTest extends TestCase
{

    /**
     * @dataProvider provider_validReferrer
     * @param null|string $referrer
     * @param bool        $expected
     */
    public function testValidReferrer(?string $referrer, bool $expected)
    {
        $validation = new Statistics\ValidReferrer();

        $this->assertEquals($expected, $validation->isSatisfiedBy($referrer));
    }

    /**
     * Data provider for testValidReferrer
     *
     * @return array
     */
    public function provider_validReferrer(): array {
        return [
            'Valid referrer: dashboard' => [
                Statistics\Mapper::REFERRER_DASHBOARD,
                true
            ],
            'Valid referrer: homepage' => [
                Statistics\Mapper::REFERRER_HOMEPAGE,
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

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
     * @dataProvider provider_validImpressionReferrer
     * @param null|string $referrer
     * @param bool        $expected
     */
    public function testValidImpressionReferrer(?string $referrer, bool $expected)
    {
        $validation = new Statistics\ValidImpressionReferrer();

        $this->assertEquals($expected, $validation->isSatisfiedBy($referrer));
    }

    /**
     * Data provider for testValidImpressionReferrer
     *
     * @return array
     */
    public function provider_validImpressionReferrer(): array {
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
            'Valid referrer: null' => [
                null,
                false
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
    /**
     * @dataProvider provider_validOpenReferrer
     * @param null|string $referrer
     * @param bool        $expected
     */
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
    public function provider_validOpenReferrer(): array {
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

<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Resource\Attachment\Filesystem\Teams\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Resource\Attachment\Filesystem\Teams\ValidTeamsMeetingUrl;

/**
 * Class ValidationMeetingUrl
 *
 * Test the vital bridge between Microsoft Teams land and Schoolbox land
 *
 * This makes sure that the meetingUrl supplied from Microsoft Land is actually
 * the one we wanted, and not some other random hackery
 *
 * @package Schoolbox\Resource\Attachment\Filesystem\Teams\Test
 */
class ValidationMeetingUrlTest extends TestCase
{

    /**
     * @param mixed $input
     * @param int   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validMeetingUrl')]
    public function testValidMeetingUrl($input, $expected)
    {
        $outcome = (new ValidTeamsMeetingUrl())->isSatisifiedBy($input);

        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidMeetingUrl
     *
     * @return array
     */
    public static function provider_validMeetingUrl(): array
    {
        return [

            /* Add other cases that you expect to be valid
             */
            'Valid Example 1' => [
                'https://teams.microsoft.com/l/meetup-join/19%3ameeting_MzUwMGQ4YjktZDVmOC00NTA4LTgyYWItYzg2MTg5OGRjZTJj%40thread.v2/0?context=%7b%22Tid%22%3a%229a9eb3a6-27e2-43bf-8831-37e8d0cc2265%22%2c%22Oid%22%3a%22de300b48-7335-4d63-9180-1f22d1396656%22%7d',    // goes into testValidExample() as $input
                true
            ],

            'Valid Example 2' => [
                'https://teams.microsoft.com/anything you might imagine, like an obama speech or something',
                true
            ],

            /* This is actually some other url format that you get when you visit teams.microsoft.com directly
             * It'll be allowed as a result of the url restrictions loosening up
             */
            'Valid Example 3' => [
                'https://teams.microsoft.com/_#/l/meetup-join/19:meeting_OWQ3ZWUzMDYtZmYwZS00OTdlLWI3OGUtNDI2MjFiNDg1ZTE0@thread.v2/0?context=%7B%22Tid%22:%229a9eb3a6-27e2-43bf-8831-37e8d0cc2265%22,%22Oid%22:%22de300b48-7335-4d63-9180-1f22d1396656%22%7D&anon=true&deeplinkId=713824fe-e5f3-4548-de24-43f61199fa78',
                true
            ],

            /* Add other cases that you expect to be invalid
             */
            'Invalid Example 1' => [
                ' https://teams.microsoft.com/l/meetup-join/19%3ameeting_MzUwMGQ4YjktZDVmOC00NTA4LTgyYWItYzg2MTg5OGRjZTJj%40thread.v2/0?context=%7b%22Tid%22%3a%229a9eb3a6-27e2-43bf-8831-37e8d0cc2265%22%2c%22Oid%22%3a%22de300b48-7335-4d63-9180-1f22d1396656%22%7d',
                false
            ],

            'Invalid Example 2' => [
                'https://www.schoolbox.comm.au',
                false
            ],

            'Invalid Example 3' => [
                '',
                false
            ],

            'Invalid Example 4' => [
                '    ',
                false
            ],

            'Invalid Example 5' => [
                'https://teams.microsoft.com-malicious-website.com/',
                false
            ],
        ];
    }
}

<?php
/**
 * ValidationTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\ValidBroadcast;


class ValidationTest extends TestCase
{

    /**
     * @dataProvider provider_validBroadcastRequirements
     * @param Model $news
     * @param int   $moderationRequired
     * @param int   $expected
     */
    public function testValidBroadcastRequirements(Model $news, int $moderationRequired, $expected)
    {
        $validation = new ValidBroadcast();

        $this->assertEquals($expected, $validation->isSatisfiedBy($news, $moderationRequired));
    }

    /**
     * Data provider for testValidModerationRequirements
     *
     * @return array
     */
    public function provider_validBroadcastRequirements() :array {
        $user = (new \Schoolbox\User())->setId(19);

        return [
            // Moderation required, a moderator is known
            // News article can reach the masses
            'Moderation required, moderator set' => [
                (new Model())->setTitle('cat')->setModeratedBy($user),
                1,
                true
            ],

            // Moderator must be set when moderation is required
            // When absent, the article isn't moderated; news article mustn't reach the masses
            'Moderation required, moderator not set' => [
                (new Model())->setTitle('cat'),
                1,
                false
            ],

            // Preserve last known moderator in the scenario where moderation was previously required
            // at the time, but moderation is no longer required now. News article can reach the masses.
            'Moderation not required, moderator set' => [
                (new Model())->setTitle('cat')->setModeratedBy($user),
                0,
                true
            ],

            // If there was never a moderator for this article, and moderation is not required now,
            // news article can reach the masses. There are no restrictions.
            'Moderation not required, moderator not set' => [
                (new Model())->setTitle('cat'),
                0,
                true
            ],
        ];
    }
}



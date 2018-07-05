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
use Schoolbox\Comms\News\Policy;
use Schoolbox\Comms\News\ValidArticle;
use Schoolbox\Comms\News\ValidBroadcast;
use Schoolbox\Comms\News\ValidDateOffset;


class ValidationTest extends TestCase
{

    /**
     * @dataProvider provider_validBroadcastRequirements
     * @param Model $article
     * @param bool  $expected
     */
    public function testValidBroadcastRequirements(Model $article, bool $expected)
    {
        $validation = new ValidBroadcast();

        $this->assertEquals($expected, $validation->isSatisfiedBy($article));
    }

    /**
     * Data provider for testValidModerationRequirements
     *
     * @return array
     */
    public function provider_validBroadcastRequirements(): array {
        $user = (new \Schoolbox\User())->setId(19);
        $date = (new \Alaress_Date_Format())->setDateTime(2018, 1, 1, 10, 12, 14);

        return [
            // Moderation required, an acceptor is known
            // News article can reach the masses
            'Moderation required, both acceptor details set' => [
                (new Model())->setTitle('cat')->setAcceptedBy($user)->setAcceptedAt($date),
                true
            ],

            // Moderation required, an acceptor is known, but no date is available for some reason
            // News article cannot reach the masses
            'Moderation required, acceptor user set' => [
                (new Model())->setTitle('cat')->setAcceptedBy($user),
                false
            ],

            // Moderation required, an acceptor is known, but no acceptor is available for some reason
            // News article cannot reach the masses
            'Moderation required, acceptor date set' => [
                (new Model())->setTitle('cat')->setAcceptedBy(null)->setAcceptedAt($date),
                false
            ],

            // Acceptor must be set when moderation is required
            // When absent, the article isn't accepted; news article mustn't reach the masses
            'Moderation required, acceptor not set' => [
                (new Model())->setTitle('cat'),
                false
            ],

            // Preserve last known acceptor in the scenario where moderation was previously required
            // at the time, but moderation is no longer required now. News article can reach the masses.
            'Moderation not required, acceptor set' => [
                (new Model())->setTitle('cat')->setAcceptedBy($user)->setAcceptedAt($date),
                true
            ],

            // Edge case that no longer exists; when moderation isn't required, authors are their own
            // acceptor. Otherwise, someone else is the acceptor. In either situation this shouldn't arise
            'Moderation not required, acceptor not set' => [
                (new Model())->setTitle('cat'),
                false
            ],
        ];
    }

    /**
     * @dataProvider provider_validArticle
     * @param Model $article
     * @param int   $expected
     */
    public function testValidArticle(Model $article, $expected)
    {
        $validation = new ValidArticle();

        $this->assertEquals($expected, $validation->isSatisfiedBy($article));
    }

    /**
     * Data provider for testValidArticle
     *
     * @return array
     */
    public function provider_validArticle(): array {
        return [
            'Heading is set' => [
                (new Model())->setTitle('cat'),
                true
            ],
            'Heading is missing' => [
                new Model(),
                false
            ],
        ];
    }


    /**
     * @dataProvider provider_validDateOffset
     * @param Policy\Model $policy
     * @param int   $expected
     */
    public function testValidDateOffset(Policy\Model $policy, $expected)
    {
        $validation = new ValidDateOffset();

        $this->assertEquals($expected, $validation->isSatisfiedBy($policy));
    }

    /**
     * Data provider for testValidDateOffset
     *
     * @return array
     */
    public function provider_validDateOffset(): array {
        return [
            'Valid (+1 day)' => [
                (new Policy\Model())->setValue('+1 day'),
                true
            ],
            'Valid (+1 week)' => [
                (new Policy\Model())->setValue('+1 week'),
                true
            ],
            'Valid (+1 month)' => [
                (new Policy\Model())->setValue('+1 month'),
                true
            ],
            'Valid (tomorrow)' => [
                (new Policy\Model())->setValue('tomorrow'),
                true
            ],
            'Invalid (empty string)' => [
                (new Policy\Model())->setValue(''),
                false
            ],
            'Invalid (whitespace)' => [
                (new Policy\Model())->setValue('   '),
                false
            ],
            'Invalid 1' => [
                (new Policy\Model())->setValue('no particular time'),
                false
            ],
            'Invalid 2' => [
                (new Policy\Model())->setValue('later'),
                false
            ],
            'Invalid 3' => [
                (new Policy\Model())->setValue('a few days later'),
                false
            ],
            'Invalid (trash 1)' => [
                (new Policy\Model())->setValue('ALKJFLj297245u2o34jklhlsdf'),
                false
            ],
            'Invalid (trash 2)' => [
                (new Policy\Model())->setValue('@#(O$%&U@(*$U(@JKO$HJO@#H$(*@#YU( 9284 2894 y9284 y928y97&$(!@&$'),
                false
            ],
        ];
    }
}



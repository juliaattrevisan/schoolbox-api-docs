<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Tests\Acceptance;

/**
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /usr/share/schoolbox && ./vendor/codeception/codeception/codecept --steps run acceptance FixtureCest
 */

use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Fixture;
use Exception;

class FixtureCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function _before(AcceptanceTester $i)
    {
        exec('cd /usr/share/schoolbox/tools/notif; sudo php /usr/share/schoolbox/test/socket/destroy-rabbit-data.php;');
        $i->logout();
        $i->loginAsAdmin();
    }

    /**
     * Clean up after every test
     *
     * @param AcceptanceTester $i
     */
    public function _after(AcceptanceTester $i)
    {
    }

    /**
     * Test that match notifications are triggered properly
     *
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function matchNotifications(AcceptanceTester $i): void
    {
        $fixturePage = new Fixture($i);

        $i->amGoingTo('Match in past - No notification');
        $matchName1 = 'First Cest Match';
        $start = new \DateTime();
        $start->modify('-120 minutes');
        $finish = new \DateTime();
        $finish->modify('-60 minutes');
        $fixturePage->createMatch(
            [
                Fixture::SELECTOR_NAME => $matchName1,
                Fixture::SELECTOR_START_AT => $start,
                Fixture::SELECTOR_FINISH_AT => $finish
            ],
            [
                Fixture::SELECTOR_JS_VENUE => Fixture::VENUE_OAKLEIGH_RECREATION,
                Fixture::SELECTOR_JS_HOME_TEAM => Fixture::TEAM_WARRIORS,
                Fixture::SELECTOR_JS_AWAY_TEAM => Fixture::TEAM_RAIDERS
            ]
        );

        $i->amGoingTo('wait 5 seconds and should receive the notification');
        $i->wait(5);
        $i->amGoingTo('As Jack Coles, check notification');
        $fixturePage->assertNotifNotExists('You have an upcoming Basketball match ' . $matchName1);

        $i->amGoingTo('Match in 24 hrs - no change and save - No notification');
        $matchName2 = 'Second Cest Match';
        $start = new \DateTime();
        $start->modify('+30 minutes');
        $finish = new \DateTime();
        $finish->modify('+120 minutes');

        $matchId = $fixturePage->createMatch(
            [
                Fixture::SELECTOR_NAME => $matchName2,
                Fixture::SELECTOR_START_AT => $start,
                Fixture::SELECTOR_FINISH_AT => $finish
            ],
            [
                Fixture::SELECTOR_JS_VENUE => Fixture::VENUE_OAKLEIGH_RECREATION,
                Fixture::SELECTOR_JS_HOME_TEAM => Fixture::TEAM_WARRIORS,
                Fixture::SELECTOR_JS_AWAY_TEAM => Fixture::TEAM_RAIDERS
            ]
        );

        $fixturePage->assertNotifExists('You have an upcoming Basketball match ' . $matchName2);

        $fixturePage->updateMatch($matchId, [], []);
        $i->amGoingTo('wait 5 seconds and should receive the notification');
        $i->wait(5);
        $fixturePage->assertNotifNotExists('Your upcoming Basketball match ' . $matchName2 . ' has been updated');

        $i->amGoingTo('Match in 24 hrs - change title and save - Updated notification');
        $matchName3 = 'Third Cest Match';
        $fixturePage->updateMatch($matchId, [Fixture::SELECTOR_NAME => $matchName3], []);
        $i->amGoingTo('wait 5 seconds and should receive the notification');
        $i->wait(5);
        $fixturePage->assertNotifExists('Your upcoming Basketball match ' . $matchName3 . ' has been updated');

        $i->amGoingTo('Match in 24 hrs - change date - Updated notification');
        $fixturePage->updateMatch($matchId, [Fixture::SELECTOR_START_AT => $start->modify('+45 minutes')], []);
        $i->amGoingTo('wait 5 seconds and should receive the notification');
        $i->wait(5);
        $fixturePage->assertNotifExists('Your upcoming Basketball match ' . $matchName3 . ' has a new start time');

        $i->amGoingTo('Match in 24 hrs - add itinerary and save');
        $matchName4 = 'Fourth Cest Match';
        $start = new \DateTime();
        $start->modify('+30 minutes');
        $finish->modify('+120 minutes');

        $matchId4 = $fixturePage->createMatch(
            [
                Fixture::SELECTOR_NAME => $matchName4,
                Fixture::SELECTOR_START_AT => $start,
                Fixture::SELECTOR_FINISH_AT => $finish
            ],
            [
                Fixture::SELECTOR_JS_VENUE => Fixture::VENUE_OAKLEIGH_RECREATION,
                Fixture::SELECTOR_JS_HOME_TEAM => Fixture::TEAM_WARRIORS,
                Fixture::SELECTOR_JS_AWAY_TEAM => Fixture::TEAM_RAIDERS
            ]
        );
        $fixturePage->addItinerary($matchId4);
        $i->amGoingTo('wait 5 seconds and should receive the notification');
        $i->wait(5);
        $fixturePage->assertNotifExists('Your upcoming Basketball match ' . $matchName4 . ' itinerary has been changed');

        $i->amGoingTo('Match in 24 hrs - remove itinerary and save');
        $matchName5 = 'Fifth Cest Match';
        $start = new \DateTime();
        $start->modify('+30 minutes');
        $finish->modify('+120 minutes');

        $matchId5 = $fixturePage->createMatch(
            [
                Fixture::SELECTOR_NAME => $matchName5,
                Fixture::SELECTOR_START_AT => $start,
                Fixture::SELECTOR_FINISH_AT => $finish
            ],
            [
                Fixture::SELECTOR_JS_VENUE => Fixture::VENUE_OAKLEIGH_RECREATION,
                Fixture::SELECTOR_JS_HOME_TEAM => Fixture::TEAM_WARRIORS,
                Fixture::SELECTOR_JS_AWAY_TEAM => Fixture::TEAM_RAIDERS
            ]
        );
        $fixturePage->deleteItinerary($matchId5);
        $i->amGoingTo('wait 5 seconds and should receive the notification');
        $i->wait(5);
        $fixturePage->assertNotifExists('Your upcoming Basketball match ' . $matchName5 . ' itinerary has been changed');

        $i->amGoingTo('Match in future - No instant notification');
        $matchName1 = 'Sixth Cest Match';
        $start = new \DateTime();
        $start->modify('+ 1 day 10 minutes');
        $finish->modify('+ 1 day 120 minutes');
        $matchId = $fixturePage->createMatch(
            [
                Fixture::SELECTOR_NAME => $matchName1,
                Fixture::SELECTOR_START_AT => $start,
                Fixture::SELECTOR_FINISH_AT => $finish,
            ],
            [
                Fixture::SELECTOR_JS_VENUE => Fixture::VENUE_OAKLEIGH_RECREATION,
                Fixture::SELECTOR_JS_HOME_TEAM => Fixture::TEAM_WARRIORS,
                Fixture::SELECTOR_JS_AWAY_TEAM => Fixture::TEAM_RAIDERS
            ]
        );

        $i->amGoingTo('wait 5 seconds and should receive the notification');
        $i->wait(5);
        $fixturePage->assertNotifNotExists('You have an upcoming Basketball match ' . $matchName1);

        $fixturePage->updateMatch($matchId, [], []);
        $i->amGoingTo('wait 5 seconds and should receive the notification');
        $i->wait(5);
        $fixturePage->assertNotifNotExists('Your upcoming Basketball match ' . $matchName1 . ' has been updated');

        $i->amGoingTo('Change title and save - Updated notification');
        $matchName2 = 'Third Cest Modified Sixth';
        $fixturePage->updateMatch($matchId, [
            Fixture::SELECTOR_NAME => $matchName2,
            Fixture::SELECTOR_START_AT => (new \DateTime())->modify('+ 1 day 2 minutes'),
        ], []);
        $i->amGoingTo('wait 5 seconds and should receive the notification');
        $i->wait(5);
        $fixturePage->assertNotifNotExists('Your upcoming Basketball match ' . $matchName2 . ' has been updated');

        $i->amGoingTo('wait 2 minutes and should receive upcoming notification');
        $i->wait(120);
        $fixturePage->assertNotifExists('You have an upcoming Basketball match ' . $matchName2);
        $fixturePage->assertNotifNotExists('Your upcoming Basketball match ' . $matchName2 . ' has been updated');
    }
}

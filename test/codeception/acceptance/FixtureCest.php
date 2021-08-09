<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /vagrant && ./vendor/codeception/codeception/codecept --steps run acceptance FixtureCest
 */

use Page\Fixture;
use Page\Common;

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
        exec('cd /vagrant/tools/notif; sudo php /vagrant/test/socket/destroy-rabbit-data.php;');
        c
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
     */
    public function matchNotifications(AcceptanceTester $i)
    {
        $fixturePage = new Fixture($i);

        //== Match in past - No notification ==
        $matchName1 = 'First Cest Match';
        $start = new \DateTime();
        $start->modify('-120 minutes');
        $fixturePage->createMatch(
            [
                Fixture::SELECTOR_NAME => $matchName1,
                Fixture::SELECTOR_START_AT => $start
            ],
            [
                Fixture::SELECTOR_JS_VENUE => Fixture::VENUE_HAILEYBURY,
                Fixture::SELECTOR_JS_HOME_TEAM => Fixture::TEAM_FIRST_XVIII,
                Fixture::SELECTOR_JS_AWAY_TEAM => Fixture::TEAM_SECOND_XVIII
            ]
        );

        $fixturePage->assertNotifNotExists('You have an upcoming AFL match ' . $matchName1);

        //== Match in 24 hrs - no change and save - No notification ==
        $matchName2 = 'Second Cest Match';
        $start = new \DateTime();
        $start->modify('+30 minutes');

        $matchId = $fixturePage->createMatch(
            [
                Fixture::SELECTOR_NAME => $matchName2,
                Fixture::SELECTOR_START_AT => $start
            ],
            [
                Fixture::SELECTOR_JS_VENUE => Fixture::VENUE_HAILEYBURY,
                Fixture::SELECTOR_JS_HOME_TEAM => Fixture::TEAM_FIRST_XVIII,
                Fixture::SELECTOR_JS_AWAY_TEAM => Fixture::TEAM_SECOND_XVIII
            ]
        );

        $fixturePage->assertNotifExists('You have an upcoming AFL match ' . $matchName2);

        $fixturePage->updateMatch($matchId, [], []);
        $fixturePage->assertNotifNotExists('Your upcoming AFL match ' . $matchName2 . ' has been updated');

        //== Match in 24 hrs - change title and save - Updated notification ==
        $matchName3 = 'Third Cest Match';
        $fixturePage->updateMatch($matchId, [Fixture::SELECTOR_NAME => $matchName3], []);
        $fixturePage->assertNotifExists('Your upcoming AFL match ' . $matchName3 . ' has been updated');

        //== Match in 24 hrs - change date - Updated notification ==
        $fixturePage->updateMatch($matchId, [Fixture::SELECTOR_START_AT => $start->modify('+45 minutes')], []);
        $fixturePage->assertNotifExists('Your upcoming AFL match ' . $matchName3 . ' has a new start time');

        //== Match in 24 hrs - add itinerary and save
        $matchName4 = 'Fourth Cest Match';
        $start = new \DateTime();
        $start->modify('+30 minutes');

        $matchId4 = $fixturePage->createMatch(
            [
                Fixture::SELECTOR_NAME => $matchName4,
                Fixture::SELECTOR_START_AT => $start
            ],
            [
                Fixture::SELECTOR_JS_VENUE => Fixture::VENUE_HAILEYBURY,
                Fixture::SELECTOR_JS_HOME_TEAM => Fixture::TEAM_FIRST_XVIII,
                Fixture::SELECTOR_JS_AWAY_TEAM => Fixture::TEAM_SECOND_XVIII
            ]
        );
        $fixturePage->addItinerary($matchId4);
        $fixturePage->assertNotifExists('Your upcoming AFL match ' . $matchName4 . ' itinerary has been changed');

        //== Match in 24 hrs - remove itinerary and save
        $matchName5 = 'Fifth Cest Match';
        $start = new \DateTime();
        $start->modify('+30 minutes');

        $matchId5 = $fixturePage->createMatch(
            [
                Fixture::SELECTOR_NAME => $matchName5,
                Fixture::SELECTOR_START_AT => $start
            ],
            [
                Fixture::SELECTOR_JS_VENUE => Fixture::VENUE_HAILEYBURY,
                Fixture::SELECTOR_JS_HOME_TEAM => Fixture::TEAM_FIRST_XVIII,
                Fixture::SELECTOR_JS_AWAY_TEAM => Fixture::TEAM_SECOND_XVIII
            ]
        );
        $fixturePage->deleteItinerary($matchId5);
        $fixturePage->assertNotifExists('Your upcoming AFL match ' . $matchName5 . ' itinerary has been changed');

        //== Match in future - No instant notification ==
        $matchName1 = 'Sixth Cest Match';
        $start = new \DateTime();
        $start->modify('+ 1 day 2 minutes');
        $matchId = $fixturePage->createMatch(
            [
                Fixture::SELECTOR_NAME => $matchName1,
                Fixture::SELECTOR_START_AT => $start
            ],
            [
                Fixture::SELECTOR_JS_VENUE => Fixture::VENUE_HAILEYBURY,
                Fixture::SELECTOR_JS_HOME_TEAM => Fixture::TEAM_FIRST_XVIII,
                Fixture::SELECTOR_JS_AWAY_TEAM => Fixture::TEAM_SECOND_XVIII
            ]
        );

        $fixturePage->assertNotifNotExists('You have an upcoming AFL match ' . $matchName1);

        $fixturePage->updateMatch($matchId, [], []);
        $fixturePage->assertNotifNotExists('Your upcoming AFL match ' . $matchName1 . ' has been updated');

        //Change title and save - Updated notification ==
        $matchName2 = 'Third Cest Modified Sixth';
        $fixturePage->updateMatch($matchId, [Fixture::SELECTOR_NAME => $matchName2], []);
        $fixturePage->assertNotifNotExists('Your upcoming AFL match ' . $matchName2 . ' has been updated');

        // wait 2 minutes and should receive upcoming notification
        $i->wait(120);
        $fixturePage->assertNotifExists('You have an upcoming AFL match ' . $matchName2);
        $fixturePage->assertNotifNotExists('Your upcoming AFL match ' . $matchName2 . ' has been updated');
    }
}

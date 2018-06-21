<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /vagrant && ./vendor/bin/codecept run --steps acceptance FixtureCest
 * ./vendor/codeception/codeception/codecept --steps run acceptance FixtureCest
 */

use Page\Fixture;

class FixtureCest
{
    public function _before(AcceptanceTester $i)
    {
        $i->loginAsAdmin();
    }

    public function _after(AcceptanceTester $i)
    {
    }

    // tests
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
            ]);

        $fixturePage->assertNofifNotExists('You have an upcoming AFL match ' . $matchName1);

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
            ]);

        $fixturePage->assertNofifExists('You have an upcoming AFL match ' . $matchName2);

        $fixturePage->updateMatch($matchId, [], []);
        $fixturePage->assertNofifNotExists('Your upcoming AFL match ' . $matchName2 . ' has been updated');

        //== Match in 24 hrs - change title and save - Updated notification ==
        $matchName3 = 'Third Cest Match';
        $fixturePage->updateMatch($matchId, [Fixture::SELECTOR_NAME => $matchName3], []);
        $fixturePage->assertNofifExists('Your upcoming AFL match ' . $matchName3 . ' has been updated');

        //== Match in 24 hrs - change date - Updated notification ==
        $fixturePage->updateMatch($matchId, [Fixture::SELECTOR_START_AT => $start->modify('+45 minutes')], []);
        $fixturePage->assertNofifExists('Your upcoming AFL match ' . $matchName3 . ' has a new start time');

        // Match in 24 hrs - change itenerary and save

        //== Match in 24 hrs - add itenerary and save
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
            ]);
        $fixturePage->addItenerary($matchId4);
        $fixturePage->assertNofifExists('Your upcoming AFL match ' . $matchName4 . ' has been updated');

        //== Match in 24 hrs - remove itenerary and save
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
            ]);
        $fixturePage->deleteItenerary($matchId5);

    }

}

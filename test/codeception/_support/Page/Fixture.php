<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Page;

use Codeception\Util\Locator;

class Fixture
{
    public const URL_CREATE_MATCH = '/fixtures/divisions/{division_id}/matches/new?season={season_id}';
    public const URL_UPDATE_MATCH = '/fixtures/matches/{match_id}/edit';
    public const URL_SEASON_ID = '{season_id}';
    public const URL_DIVISION_ID = '{division_id}';
    public const URL_MATCH_ID = '{match_id}';

    public const SELECTOR_NAME = 'input[name="name"]';
    public const SELECTOR_START_AT = '#startAtDate';
    public const SELECTOR_JS_VENUE = '#venue';
    public const SELECTOR_JS_HOME_TEAM = '#homeTeam';
    public const SELECTOR_JS_AWAY_TEAM = '#awayTeam';

    public const VENUE_HAILEYBURY = 2;
    public const TEAM_FIRST_XVIII = 8;
    public const TEAM_SECOND_XVIII = 9;

    /**
     * @var AcceptanceTester
     */
    protected $tester;

    public function __construct(\AcceptanceTester $i)
    {
        $this->tester = $i;
    }

    /**
     * Gets url to create a match
     * @param int $divisionId
     * @param int $seasonId
     * @return string the URL
     */
    public static function getCreateMatchUrl(int $divisionId, int $seasonId): string
    {
        return str_replace(
            [self::URL_SEASON_ID, self::URL_DIVISION_ID],
            [$seasonId, $divisionId],
            self::URL_CREATE_MATCH
        );
    }

    /**
     * Gets url to update a match
     * @param int $matchId
     * @return string
     */
    public static function getUpdateMatchUrl(int $matchId): string
    {
        return str_replace(self::URL_MATCH_ID, $matchId, self::URL_UPDATE_MATCH);
    }

    /**
     * Creates a match step by step
     * @param array $fields
     * @param array $jsFields
     * @return mixed match ID, if created without problem
     */
    public function createMatch(array $fields, array $jsFields)
    {
        $i = $this->tester;
        // AFL - Open AFL
        $i->amOnPage(self::getCreateMatchUrl(4, 4));
        $i->see('CREATE MATCH');

        $startAt = '';
        $startAtDb = '';
        foreach ($fields as $fieldName => $fieldValue) {
            if ($fieldValue instanceof \DateTime) {
                $startAt = $fieldValue->format('d/m/Y h:ia');
                $startAtDb = $fieldValue->format('Y-m-d H:i');
                $fieldValue = $startAt;
            }
            $i->fillField($fieldName, $fieldValue);
        }
        $i->click('[data-item-add]');
        //<input type="text" name="itinerary[date][]" id="itineraryDate-new-1529450182910" placeholder="Date" data-itinerary-date="" data-datepicker-time="" value="" data-datepicker-id="2">
        $i->fillField(['name' => 'itinerary[date][]'], $startAt);
        $i->fillField(['name' => 'itinerary[description][]'], 'aaa');
        $i->wait(3);

        // $i->click('.selectize-input');
        foreach ($jsFields as $fieldName => $fieldValue) {
            $i->executeJS('$("' . $fieldName . '").val(' . $fieldValue . ');');
        }

        $i->click('#matchForm [type=submit][value="Create Match"]');
        $i->waitForText('Seasons');
        $matchIds = $i->grabColumnFromDatabase(
            'sportsfixtures_match',
            'id',
            ['name' => $fields[self::SELECTOR_NAME], 'start_at' => $startAtDb]
        );
        return $matchIds[0];
    }

    /**
     * Updates a match step by step
     * @param $matchId
     * @param array $changes
     * @param array $jsChanges
     */
    public function updateMatch($matchId, array $changes, array $jsChanges)
    {
        $i = $this->tester;
        $i->amOnPage(self::getUpdateMatchUrl($matchId));
        $i->see('MODIFY MATCH');

        $now = new \DateTime();
        foreach ($changes as $fieldName => $fieldValue) {
            if ($fieldValue instanceof \DateTime) {
                $fieldValue = $fieldValue->format('d/m/Y h:ia');
            }
            $i->fillField($fieldName, $fieldValue);
        }
        $i->click('#matchForm [type=submit][value="Modify Match"]');
        $i->wait(3);
    }


    /**
     * Updates a match to add an itinerary item
     * @param $matchId
     */
    public function addItinerary($matchId)
    {
        $i = $this->tester;
        $i->amOnPage(self::getUpdateMatchUrl($matchId));
        $i->see('MODIFY MATCH');

        $now = new \DateTime();
        $startAt = $now->format('d/m/Y h:ia');

        $i->executeJS('$("[data-item-add]").click();');
        $i->fillField(Locator::elementAt('[data-item-list] li input[type="text"]', 3), $startAt);
        $i->fillField(Locator::elementAt('[data-item-list] li input[type="text"]', 4), 'lalala');
        $i->executeJS('$("[data-item-add]").click();');
        $i->click('#matchForm [type=submit][value="Modify Match"]');
        $i->wait(3);
    }

    /**
     * Updates a match to delete an itinerary item
     * @param $matchId
     */
    public function deleteItinerary($matchId)
    {
        $i = $this->tester;
        $i->amOnPage(self::getUpdateMatchUrl($matchId));
        $i->see('MODIFY MATCH');
        $i->click(Locator::elementAt('[data-item-list] [data-delete]', 1));
        $i->click('#matchForm [type=submit][value="Modify Match"]');
        $i->wait(3);
    }

    /**
     * Verifies that the notification exists
     * @param $matchId
     */
    public function assertNotifExists($notif)
    {
        $i = $this->tester;
        $i->amOnPage('/messages');
        $i->dontSee($notif);
        $i->impersonate('Rachel Rhodes');
        $i->amOnPage('/messages');
        $i->see($notif);
        $i->impersonateAnyUser('Schoolbox Admin');
    }

    /**
     * Verifies that the notification does not exist
     * @param $matchId
     */
    public function assertNotifNotExists($notif)
    {
        $i = $this->tester;
        $i->amOnPage('/messages');
        $i->dontSee($notif);
        $i->impersonate('Rachel Rhodes');
        $i->amOnPage('/messages');
        $i->dontSee($notif);
        $i->impersonateAnyUser('Schoolbox Admin');
    }
}

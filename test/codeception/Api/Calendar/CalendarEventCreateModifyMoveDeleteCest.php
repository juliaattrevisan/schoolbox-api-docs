<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\Calendar;

use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Event\Attendance\Model as EventAttendanceModel;
use UnexpectedValueException;
use Tests\Support\ApiTester;

/**
 * calendar@event@attendance@{id}@create.yaml
 * /usr/share/schoolbox/vendor/bin/codecept run api CalendarEventAttendanceCreateCest
 */
class CalendarEventCreateModifyMoveDeleteCest
{
    const ALARESS_USER_ID = 1;

    private array $createdEvent;

    private array $test = [
        'test' => 1
    ];

    private string $apiPathForCreate = '/calendar/event/create';

    private array $eventMinimalRequiredParams = [
        'allDay' => true,
        'endDate' => '24/09/2024',
        'level' => 1,
        'publishDate' => '25/09/2024 7:00pm', // Current configured timezone is assumed.
        'startDate' => '24/09/2024',
        'title' => "St Swivven's Day",
        'type' => 1,
    ];

    /**
     * @throws ServiceNotFound
     */
    public function _before(ApiTester $i): void
    {
        $i->getToken(self::ALARESS_USER_ID);
    }

    /**
     * Create an event with only the required parameters, then check whether:
     *  - those parameters were set correctly, and
     *  - whether the other parameters were set to the expected default values.
     *
     * Then create an event with fewer than the required parameters, and expect that attempt to fail.
     *
     * @param ApiTester $i
     */
    public function testCreateEventWithMinimalParameters(ApiTester $i): void
    {
        // Create an event, setting the required parameters only
        $i->amGoingTo("create an event using the API, with minimal required parameters ($this->apiPathForCreate)...");
        $i->sendPost($this->apiPathForCreate, $this->eventMinimalRequiredParams);
        $createdEventJson = $i->grabResponse();
        $this->createdEvent = json_decode($createdEventJson, true);
        if ($this->createdEvent === null) {
            throw new UnexpectedValueException("Could not decode JSON '$createdEventJson'.'");
        }
        if (array_key_exists('message', $this->createdEvent)) {
            throw new UnexpectedValueException("Error message from server: '{$this->createdEvent['message']}'");
        }

        // In created event, check keys with non-array values.
        $timezoneOffset = date('P'); // E.g. '+10:00'.
        $expectedValueByKey = [
            'allDay' => true,
            'efficacy' => 'self',
            'title' => "St Swivven's Day",
            'startDate' => "2024-09-24T00:00:00$timezoneOffset",
            'endDate' => "2024-09-24T23:59:59$timezoneOffset",
            'repeat' => '', // TODO: This value ought to be a null instead of a blank string.
            'repeat_until' => "2024-09-24T23:59:59$timezoneOffset",
            'location' => '',
            'detail' => '',
            'publishDate' => "2024-09-25T19:00:00$timezoneOffset",
            'level' => Event::ACCESS_PERSONAL,
            'attendance' => EventAttendanceModel::LEVEL_NO_ATTENDANCE,
            'maxAttendees' => null,
            'showAttendees' => false,
        ];
        foreach ($expectedValueByKey as $key => $expectedValue) {
            $i->assertSame($this->createdEvent[$key], $expectedValue, "Testing key '$key'.");
        }

        // In created event, check keys whose values are expected to be arrays.
        $i->assertSame(count($this->createdEvent['roles']), 0, "Testing key 'roles'.");
        $i->assertSame(count($this->createdEvent['campuses']), 0, "Testing key 'campuses'.");
        $i->assertSame(count($this->createdEvent['folders']), 0, "Testing key 'folders'.");
        $i->assertSame(count($this->createdEvent['guests']), 0, "Testing key 'guests'.");

        // In created event, check keys whose values are expected to be stubs.
        $i->assertSame($this->createdEvent['user']['id'], self::ALARESS_USER_ID, "Testing key 'user'.");
        $i->assertSame($this->createdEvent['author']['id'], self::ALARESS_USER_ID, "Testing key 'author'.");
        $i->assertSame($this->createdEvent['type']['id'], 1, "Testing key 'type'.");
    }

    /**
     * Attempt to create an event with fewer than the required parameters, and expect that attempt to fail.
     *
     * @param ApiTester $i
     */
    public function testCreateEventWithOneFewerThanTheMinimalParameters(ApiTester $i): void
    {
        $randomParamKeyToRemove = array_rand($this->eventMinimalRequiredParams, 1);
        $minimalRequiredParamsMinusOne = $this->eventMinimalRequiredParams;
        unset($minimalRequiredParamsMinusOne[$randomParamKeyToRemove]);
        $i->amGoingTo("create an event using the API, leaving out required key '$randomParamKeyToRemove' ($this->apiPathForCreate)...");
        $i->sendPost($this->apiPathForCreate, $minimalRequiredParamsMinusOne);
        $createdEventJson = $i->grabResponse();
        $createdEvent = json_decode($createdEventJson, true);
        if ($createdEvent === null) {
            throw new UnexpectedValueException("Could not decode JSON '$createdEventJson'.'");
        }
        $i->assertTrue(str_starts_with($createdEvent['message'], 'Missing required key'));
    }

    /**
     * @param ApiTester $i
     * @return void
     * @throws UnexpectedValueException
     */
    public function testModifyEvent(ApiTester $i): void
    {
        $userJackColes      = $i->getUserByUsername('jcoles');
        $userJanaReed       = $i->getUserByUsername('jreed');
        $timezoneOffset     = date('P'); // E.g. '+10:00'.
        $eventModifications = [
            // Fields that can be modified without affecting other fields.
            'user'          => ['id' => $userJackColes->getId()],
            'author'        => ['id' => $userJanaReed->getId()],
            'type'          => ['id' => 2],
            'title'         => 'Festivus',
            'location'      => 'This is where it all happens.',
            'detail'        => 'These are the details.',
            'publishDate'   => "2024-09-28T00:00:00$timezoneOffset",
            'maxAttendees'  => 5,
            'showAttendees' => true,
            'attendance'    => EventAttendanceModel::LEVEL_OPEN_ATTENDANCE,
            'startDate'     => "2024-09-22T00:00:00$timezoneOffset", // Move startDate backwards, so endDate need not change.
            'endDate'       => "2024-09-24T23:59:59$timezoneOffset", // Move end date forwards, so startDate need not change.
        ];

        // -----------------------------------------------------------------------------------------------------------//
        // Run each of the $eventModifications in sequence, locally and via the API.
        // After each modification, check that the local event matches the API event.
        // -----------------------------------------------------------------------------------------------------------//
        $localEvent = $this->createdEvent;
        $localEventId = $localEvent['id'];
        foreach ($eventModifications as $key => $newValue) {
            $i->comment("Testing modification of $key...");

            // Modify local event.
            $localEvent[$key] = $newValue;

            // Modify API event.
            $i->sendPost("/calendar/event/$localEventId/modify", [$key => $newValue]);
            $apiEventJson = $i->grabResponse();
            $apiEvent = json_decode($apiEventJson, true);
            if ($apiEvent === null) {
                throw new UnexpectedValueException("Could not decode JSON '$apiEventJson'.'");
            }
            if (array_key_exists('message', $apiEvent)) {
                throw new UnexpectedValueException("Error message from server: '{$apiEvent['message']}'");
            }

            $i->assertTrue($this->eventArraysAreEqual($localEvent, $apiEvent));
        }

        // -----------------------------------------------------------------------------------------------------------//
        // Test dependent fields: {attendance, guests} by inviting guests when attendance type is 'none'
        // -----------------------------------------------------------------------------------------------------------//
        // Note the current modify event form can make this contradictory request, so the request cannot be rejected
        // first fixing that form.  For now, just make sure the server does not save the guests.
        $i->amGoingTo("attempt to invite guests to the event when attendance type is 'none'.");
        $newAttendance = EventAttendanceModel::LEVEL_NO_ATTENDANCE;
        $newGuests = [['id' => $userJackColes->getId()], ['id' => $userJanaReed->getId()]];
        $i->sendPost(
            "/calendar/event/$localEventId/modify",
            [
                'attendance' => $newAttendance,
                'guests' => $newGuests,
            ]
        );
        $localEvent['attendance'] = $newAttendance;
        $localEvent['guests'] = [];
        $apiEventJson = $i->grabResponse();
        $apiEvent = json_decode($apiEventJson, true);
        if ($apiEvent === null) {
            throw new UnexpectedValueException("Could not decode JSON '$apiEventJson'.'");
        }
        if (array_key_exists('message', $apiEvent)) {
            throw new UnexpectedValueException("Error message from server: '{$apiEvent['message']}'");
        }
        $i->assertTrue($this->eventArraysAreEqual($localEvent, $apiEvent));

        // -----------------------------------------------------------------------------------------------------------//
        // Test dependent fields: {attendance, guests} by inviting guests when attendance type allows invitations
        // -----------------------------------------------------------------------------------------------------------//
        $i->amGoingTo('change attendance type and invite guests to the event.');
        $newAttendance = EventAttendanceModel::LEVEL_INVITE_ONLY;
        $newGuests = [['id' => $userJackColes->getId()], ['id' => $userJanaReed->getId()]];
        $i->sendPost(
            "/calendar/event/$localEventId/modify",
            [
                'attendance' => $newAttendance,
                'guests' => $newGuests,
            ]
        );
        $localEvent['attendance'] = $newAttendance;
        $localEvent['guests'] = $newGuests;
        $apiEventJson = $i->grabResponse();
        $apiEvent = json_decode($apiEventJson, true);
        if ($apiEvent === null) {
            throw new UnexpectedValueException("Could not decode JSON '$apiEventJson'.'");
        }
        if (array_key_exists('message', $apiEvent)) {
            throw new UnexpectedValueException("Error message from server: '{$apiEvent['message']}'");
        }
        $i->assertTrue($this->eventArraysAreEqual($localEvent, $apiEvent));

        // -----------------------------------------------------------------------------------------------------------//
        // Test dependent fields: {allDay, startDate, endDate, repeat_until} by creating a personal-level event.
        // -----------------------------------------------------------------------------------------------------------//
        $i->amGoingTo('change from an all-day to a not all-day event.');
        $newAllDay = false;
        $newStartDate = "2024-09-24T08:00:00$timezoneOffset";
        $newEndDate = "2024-09-24T09:30:00$timezoneOffset"; // Move the old end date forwards.
        $newEndDateEndOfDay = "2024-09-24T23:59:59$timezoneOffset"; // End of the day on the new end date.
        $i->sendPost(
            "/calendar/event/$localEventId/modify",
            [
                'allDay' => $newAllDay,
                'startDate' => $newStartDate,
                'endDate' => $newEndDate
            ]
        );
        $localEvent['allDay'] = $newAllDay;
        $localEvent['startDate'] = $newStartDate;
        $localEvent['endDate'] = $newEndDate;
        $localEvent['repeat_until'] = $newEndDateEndOfDay; // +- Since the end date has moved forwards, the repeat_until date
        $apiEventJson = $i->grabResponse();                //  \ in the $apiEvent will have moved forward to match.
        $apiEvent = json_decode($apiEventJson, true);
        if ($apiEvent === null) {
            throw new UnexpectedValueException("Could not decode JSON '$apiEventJson'.'");
        }
        if (array_key_exists('message', $apiEvent)) {
            throw new UnexpectedValueException("Error message from server: '{$apiEvent['message']}'");
        }
        $i->assertTrue($this->eventArraysAreEqual($localEvent, $apiEvent));

        // -----------------------------------------------------------------------------------------------------------//
        // Test dependent fields: {level, campuses, folders, roles} by changing personal-level to group-level event
        // -----------------------------------------------------------------------------------------------------------//
        $campuses    = $i->getCampuses();
        $roleStudent = $i->getRole('student');
        $roleStaff   = $i->getRole('staff');
        $campusStubs = [['id' => $campuses[0]->getId()], ['id' => $campuses[1]->getId()]];
        $roleStubs   = [['id' => $roleStudent->getId()], ['id' => $roleStaff->getId()]];
        $groupStubs  = [['id' => $i->getFolder()->getId()]];
        $i->amGoingTo('change from a personal-level event to a group-level event.');
        $newLevel = Event::ACCESS_GROUP;
        $i->sendPost(
            "/calendar/event/$localEventId/modify",
            [
                'level' => $newLevel,
                'folders' => $groupStubs,
                'campuses' => $campusStubs, // Try adding irrelevant campuses.  These should be ignored for group-level events.
                'roles' => $roleStubs, // Try adding irrelevant roles.  These should be ignored for group-level events.
            ]
        );
        $localEvent['level'] = $newLevel;
        $localEvent['folders'] = $groupStubs;
        $localEvent['campuses'] = []; // These will be cleared because campuses are irrelevant for group-level events.
        $localEvent['roles'] = []; // These will be cleared because roles are irrelevant for group-level events.
        $apiEventJson = $i->grabResponse();
        $apiEvent = json_decode($apiEventJson, true);
        if ($apiEvent === null) {
            throw new UnexpectedValueException("Could not decode JSON '$apiEventJson'.'");
        }
        if (array_key_exists('message', $apiEvent)) {
            throw new UnexpectedValueException("Error message from server: '{$apiEvent['message']}'");
        }
        $i->assertTrue($this->eventArraysAreEqual($localEvent, $apiEvent));

        // -----------------------------------------------------------------------------------------------------------//
        // Test dependent fields: {level, campuses, folders, roles} by changing group-level to campus-level event
        // -----------------------------------------------------------------------------------------------------------//
        $i->amGoingTo('change from a group-level event to a campus-level event.');
        $newLevel = Event::ACCESS_CAMPUS;
        $i->sendPost(
            "/calendar/event/$localEventId/modify",
            [
                'level' => $newLevel,
                'campuses' => $campusStubs,
                'roles' => $roleStubs,
                'groups' => $groupStubs, // Try adding irrelevant groups.  These should be ignored for campus-level events.
            ]
        );
        $localEvent['level'] = $newLevel;
        $localEvent['campuses'] = $campusStubs;
        $localEvent['roles'] = $roleStubs;
        $localEvent['folders'] = []; // This will be cleared because folders are irrelevant for campus-level events.
        $apiEventJson = $i->grabResponse();
        $apiEvent = json_decode($apiEventJson, true);
        if ($apiEvent === null) {
            throw new UnexpectedValueException("Could not decode JSON '$apiEventJson'.'");
        }
        if (array_key_exists('message', $apiEvent)) {
            throw new UnexpectedValueException("Error message from server: '{$apiEvent['message']}'");
        }
        $i->assertTrue($this->eventArraysAreEqual($localEvent, $apiEvent));

        // -----------------------------------------------------------------------------------------------------------//
        // Test dependent fields: {level, campuses, folders, roles} by changing group-level to campus-level event
        // -----------------------------------------------------------------------------------------------------------//
        $i->amGoingTo('change from a campus-level event to a personal-level event.');
        $newLevel = Event::ACCESS_PERSONAL;
        $i->sendPost(
            "/calendar/event/$localEventId/modify",
            [
                'level' => $newLevel,
                'campuses' => $campusStubs, // Try adding irrelevant campuses.  These should be ignored for personal-level events.
                'roles' => $roleStubs, // Try adding irrelevant roles.  These should be ignored for personal-level events.
                'groups' => $groupStubs, // Try adding irrelevant groups.  These should be ignored for personal-level events.
            ]
        );
        $localEvent['level'] = $newLevel;
        $localEvent['campuses'] = []; // This will be cleared because campuses are irrelevant for personal-level events.
        $localEvent['folders'] = []; // This will be cleared because folders are irrelevant for personal-level events.
        $localEvent['roles'] = []; // This will be cleared because roles are irrelevant for personal-level events.
        $apiEventJson = $i->grabResponse();
        $apiEvent = json_decode($apiEventJson, true);
        if ($apiEvent === null) {
            throw new UnexpectedValueException("Could not decode JSON '$apiEventJson'.'");
        }
        if (array_key_exists('message', $apiEvent)) {
            throw new UnexpectedValueException("Error message from server: '{$apiEvent['message']}'");
        }
        $i->assertTrue($this->eventArraysAreEqual($localEvent, $apiEvent));
    }

    // TODO: Maybe add another function to test event recurrence.

    /**
     *
     */
    private function eventArraysAreEqual(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }

        foreach (array_keys($a) as $key) {
            $aValue = $a[$key];
            $bValue = $b[$key];

            switch (gettype($aValue)) {
                case 'array':
                    // Compare object stubs.
                    if (!is_array($bValue)) {
                        return false;
                    }
                    if (array_key_exists('id', $aValue)) {
                        // We are dealing with single stubs.
                        if (!$this->stubsAreEqual($aValue, $bValue)) {
                            return false;
                        }
                    } else {
                        // We are dealing with arrays of stubs.  Sort both arrays before comparing.
                        if (count($aValue) !== count($bValue)) {
                            return false;
                        }

                        try {
                            usort($aValue, fn ($a, $b) => $a['id'] <=> $b['id']);
                            usort($bValue, fn ($a, $b) => $a['id'] <=> $b['id']);
                        } catch (UnexpectedValueException) {
                            // If the sort didn't work, then $aValue or $bValue is not in the expected format.
                            return false;
                        }
                        for ($i = 0; $i < count($aValue); $i++) {
                            $aStub = $aValue[$i];
                            $bStub = $bValue[$i];
                            if (!is_array($aStub) || !is_array($bStub)) {
                                throw new UnexpectedValueException('Stub is not an array.');
                            }
                            if (!$this->stubsAreEqual($aStub, $bStub)) {
                                return false;
                            }
                        }
                    }
                    break;
                case 'object':            // Fall through.
                case 'resource':          // Fall through.
                case 'resource (closed)': // Fall through.
                case 'unknown type':
                    throw new UnexpectedValueException();
                default:
                    // Compare values.
                    if ($aValue !== $bValue) {
                        return false;
                    }
            }
        }

        return true;
    }

    /**
     * In this context a 'stub' is an array containing a key 'id' with an integer value.
     * The array may contain other keys and values, but only the 'id' key/value is relevant for this comparison.
     *
     * Ignoring extra key/value pairs allows stubs that are required for API requests (e.g. ['id' => 1])
     * to be compared with fully-populated arrays that are returned from the API (e.g. ['id' => 1, ...]).
     */
    private function stubsAreEqual(array $a, array $b): bool
    {
        if (!array_key_exists('id', $a) || !array_key_exists('id', $b)) {
            throw new UnexpectedValueException("Stub does not contain an 'id' key.");
        }

        if (!is_int($a['id']) || !is_int($b['id'])) {
            throw new UnexpectedValueException("Stub's value for 'id' key is not an int.");
        }

        if ($a['id'] !== $b['id']) {
            return false;
        }

        return true;
    }
}

<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace API;

use Schoolbox\Calendar\Event;
use Schoolbox\Role;

/**
 * calendar@event@attendance@{id}@create.yaml
 * /usr/share/schoolbox/vendor/bin/codecept run api CalendarEventAttendanceCreateCest
 */
class CalendarEventAttendanceCest
{
    private array $testUsers;

    /**
     * @param \ApiTester $i
     * @return void
     */
    public function _before(\ApiTester $i)
    {
        $this->testUsers = $i->getUsers();
    }

    /**
     * @param \ApiTester $i
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testPostStaff(\ApiTester $i)
    {
        $this->testPost($i, $this->testUsers['staff'], true);
    }

    /**
     * @param \ApiTester $i
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testPostSuperuser(\ApiTester $i)
    {
        $this->testPost($i, $this->testUsers['super'], true);
    }

    /**
     * @param \ApiTester $i
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testPostStudent(\ApiTester $i)
    {
        $this->testPost($i, $this->testUsers['student'], true);
    }

    /**
     * @param \ApiTester $i
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testPostParent(\ApiTester $i)
    {
        $this->testPost($i, $this->testUsers['parent'], false);
    }

    /**
     * @param \ApiTester $i
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testPostGuest(\ApiTester $i)
    {
        $this->testPost($i, $this->testUsers['guest'], false);
    }

    /**
     * @param \ApiTester      $i
     * @param \Schoolbox\User $user
     * @param bool            $canInvite
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    private function testPost(\ApiTester $i, \Schoolbox\User $user, bool $canInvite)
    {
        $uniqid          = uniqid((string)mt_rand(), true);
        $staffInvitee    = $i->createTestUser("invitee-staff-$uniqid", Role::STAFF, false);
        $studentInvitee  = $i->createTestUser("invitee-student-$uniqid", Role::STUDENT, false);
        $parentInvitee   = $i->createTestUser("invitee-parent-$uniqid", Role::PARENT, false);
        $staffNonInvitee = $i->createTestUser("non-invitee-staff-$uniqid", Role::STAFF, false);
        $staffDecline    = $i->createTestUser("invitee-staff-decline-$uniqid", Role::STAFF, false);

        $data = [
            'guests' => implode(',', [
                $staffInvitee->getId(),
                $studentInvitee->getId(),
                $parentInvitee->getId(),
                $staffDecline->getId(),
            ]),
        ];

        $i->switchUser($user);
        $noAttendanceEvent = $i->createEvent(
            $user,
            Event::ACCESS_PERSONAL,
            Event\Attendance\Model::LEVEL_NO_ATTENDANCE,
            true
        );

        if ($canInvite) {
            $inviteHideGuestListEvent = $i->createEvent(
                $user,
                Event::ACCESS_PERSONAL,
                Event\Attendance\Model::LEVEL_INVITE_ONLY,
                false
            );
            $inviteShowGuestListEvent = $i->createEvent(
                $user,
                Event::ACCESS_PERSONAL,
                Event\Attendance\Model::LEVEL_INVITE_ONLY,
                true
            );
        }


        // Attendance disabled
        $i->sendPost("/calendar/event/attendance/{$noAttendanceEvent->getId()}/create", $data);
        $i->seeResponseCodeIs(200);
        $i->seeResponseContainsJson(['pending' => 0]);

        if ($canInvite) {
            // Attendance enabled
            $i->sendPost("/calendar/event/attendance/{$inviteHideGuestListEvent->getId()}/create", $data);
            $i->seeResponseCodeIs(200);
            $i->seeResponseContainsJson(['pending' => 4]);

            $i->sendPost("/calendar/event/attendance/{$inviteHideGuestListEvent->getId()}/delete", ['userId' => $studentInvitee->getId()]);
            $i->seeResponseCodeIs(200);
            $i->seeResponseContainsJson(['pending' => 3]);

            // Attendance enabled
            $i->sendPost("/calendar/event/attendance/{$inviteShowGuestListEvent->getId()}/create", $data);
            $i->seeResponseCodeIs(200);
            $i->seeResponseContainsJson(['pending' => 4]);
            $i->sendPost("/calendar/event/attendance/{$inviteShowGuestListEvent->getId()}/delete", ['userId' => $studentInvitee->getId()]);
            $i->seeResponseCodeIs(200);
            $i->seeResponseContainsJson(['pending' => 3]);
        }

        // Staff accepts invite
        $i->switchUser($staffInvitee);
        $i->sendPostAsJson("/calendar/event/attendance/{$noAttendanceEvent->getId()}/accept", $data);
        $i->seeResponseCodeIs(400);

        if ($canInvite) {
            $i->sendPostAsJson("/calendar/event/attendance/{$inviteHideGuestListEvent->getId()}/accept", $data);
            $i->seeResponseCodeIs(200);
            $i->seeResponseContainsJson(['guests' => []]);
            $i->seeResponseContainsJson(['pending' => 2]);
            $i->seeResponseContainsJson(['accepted' => 1]);
            $i->dontSeeResponseContains($staffDecline->getUsername());

            $i->sendPostAsJson("/calendar/event/attendance/{$inviteShowGuestListEvent->getId()}/accept", $data);
            $i->seeResponseCodeIs(200);
            $i->seeResponseContainsJson(['guests' => []]);
            $i->seeResponseContainsJson(['pending' => 2]);
            $i->seeResponseContainsJson(['accepted' => 1]);
            $i->seeResponseContains($staffDecline->getUsername());
        }

        // Staff declining
        $i->switchUser($staffDecline);
        $i->sendPostAsJson("/calendar/event/attendance/{$noAttendanceEvent->getId()}/decline", $data);
        $i->seeResponseCodeIs(400);

        if ($canInvite) {
            $i->sendPostAsJson("/calendar/event/attendance/{$inviteHideGuestListEvent->getId()}/decline", $data);
            $i->seeResponseCodeIs(200);
            $i->seeResponseContainsJson(['guests' => []]);
            $i->seeResponseContainsJson(['pending' => 1]);
            $i->seeResponseContainsJson(['accepted' => 1]);
            $i->seeResponseContainsJson(['declined' => 1]);
            $i->dontSeeResponseContains($staffInvitee->getUsername());

            $i->sendPostAsJson("/calendar/event/attendance/{$inviteShowGuestListEvent->getId()}/decline", $data);
            $i->seeResponseCodeIs(200);
            $i->seeResponseContainsJson(['guests' => []]);
            $i->seeResponseContainsJson(['pending' => 1]);
            $i->seeResponseContainsJson(['accepted' => 1]);
            $i->seeResponseContainsJson(['declined' => 1]);
            $i->seeResponseContains($staffInvitee->getUsername());
        }

        // Staff not invited
        $i->switchUser($staffNonInvitee);
        $i->sendPostAsJson("/calendar/event/attendance/{$noAttendanceEvent->getId()}/accept", $data);
        $i->seeResponseCodeIs(400);

        if ($canInvite) {
            $i->sendPostAsJson("/calendar/event/attendance/{$inviteHideGuestListEvent->getId()}/accept", $data);
            $i->seeResponseCodeIs(400);
            $i->dontSeeResponseContains($staffInvitee->getUsername());

            $i->sendPostAsJson("/calendar/event/attendance/{$inviteShowGuestListEvent->getId()}/accept", $data);
            $i->seeResponseCodeIs(400);
            $i->dontSeeResponseContains($staffInvitee->getUsername());
        }

        $i->sendGetAsJson("/calendar/event/attendance/{$noAttendanceEvent->getId()}");
        $i->seeResponseCodeIs(200);

        $i->sendPostAsJson("/calendar/event/attendance/{$noAttendanceEvent->getId()}/delete");

        $i->seeResponseCodeIs(401);

        if ($canInvite) {
            $i->switchUser($user);
            $i->sendGetAsJson("/calendar/event/attendance/{$inviteHideGuestListEvent->getId()}");
            $i->seeResponseCodeIs(200);
            $i->seeResponseContainsJson(['guests' => []]);
            $i->seeResponseContainsJson(['pending' => 1]);
            $i->seeResponseContainsJson(['accepted' => 1]);
            $i->seeResponseContainsJson(['declined' => 1]);
            $i->seeResponseContains($staffInvitee->getUsername());

            $i->sendGetAsJson("/calendar/event/attendance/{$inviteShowGuestListEvent->getId()}");
            $i->seeResponseCodeIs(200);
            $i->seeResponseContainsJson(['guests' => []]);
            $i->seeResponseContainsJson(['pending' => 1]);
            $i->seeResponseContainsJson(['accepted' => 1]);
            $i->seeResponseContainsJson(['declined' => 1]);
            $i->seeResponseContains($staffInvitee->getUsername());
        }
    }
}

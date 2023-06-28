<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\User;

use Tests\Support\ApiTester;
use Schoolbox\Resource\Folder\Model;
use Schoolbox\User;

/**
 * user@{id}@groupMembership@{groupId}.yaml
 * /usr/share/schoolbox/vendor/bin/codecept run api User/UserGroupMembershipCest
 */
class UserGroupMembershipCest
{
    private array $users;

    public function _before(ApiTester $i)
    {
        $this->users = $i->getUsers();
    }


    /**
     * Get a users settings for a group
     * @param ApiTester $i
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testGet(ApiTester $i)
    {
        /** @var Model $newGroup */
        $groupAdmin = $this->users['staff'];
        $newGroup   = $i->createGroup($groupAdmin, $this->users['student']);

        /** @var User $user */
        foreach ($this->users as $user) {
            $i->switchUser($user);

            $i->sendGet("/api/user/{$groupAdmin->getId()}/group/{$newGroup->getId()}", []);
            if ($user->isSuperuser()) {
                $i->seeResponseCodeIs(200);
                $i->seeResponseContainsJson(['membership' => 'admin']);
            } else {
                $i->seeResponseCodeIs(401);
            }
        }
    }


    /**
     * Add user to a group
     * @param ApiTester $i
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testPost(ApiTester $i)
    {
        $groupAdmin     = $this->users['staff'];
        $existingMember = $this->users['student'];
        $newMember      = $this->users['parent'];

        /** @var User $user */
        foreach ($this->users as $user) {
            /** @var Model $newGroup */
            $newGroup = $i->createGroup($groupAdmin, $existingMember);

            $i->switchUser($user);

            $data = [
                'membership'        => 'read',
                'enrolmentStatus'   => 'enroled',
                'isNotificationsOn' => true,
                'isFavourite'       => true,
            ];

            $i->sendPostAsJson("/api/user/{$newMember->getId()}/group/{$newGroup->getId()}", $data);

            if ($user->isSuperuser()) {
                $i->seeResponseCodeIs(200);

                $i->sendGet("/api/user/{$newMember->getId()}/group/{$newGroup->getId()}", []);
                $i->seeResponseContainsJson(['membership' => 'read']);
            } else {
                $i->seeResponseCodeIs(401);
            }
        }
    }


    /**
     * Update a user's settings for a group
     * @param ApiTester $i
     */
    public function testPut(ApiTester $i)
    {
        $groupAdmin     = $this->users['staff'];
        $existingMember = $this->users['student'];

        /** @var User $user */
        foreach ($this->users as $user) {
            /** @var Model $newGroup */
            $newGroup = $i->createGroup($groupAdmin, $existingMember);

            $i->switchUser($user);

            $data = [
                'membership'        => 'write',
                'enrolmentStatus'   => 'enroled',
                'isNotificationsOn' => true,
                'isFavourite'       => true,
            ];

            $i->sendPutAsJson("/api/user/{$existingMember->getId()}/group/{$newGroup->getId()}", $data);

            if ($user->isSuperuser()) {
                $i->seeResponseCodeIs(200);

                $i->sendGet("/api/user/{$existingMember->getId()}/group/{$newGroup->getId()}", []);
                $i->seeResponseContainsJson(['membership' => 'write']);
            } else {
                $i->seeResponseCodeIs(401);
            }
        }
    }


    /**
     * Partially update a user's group settings
     * @param ApiTester $i
     */
    public function testPatch(ApiTester $i)
    {
        $groupAdmin     = $this->users['staff'];
        $existingMember = $this->users['student'];

        /** @var User $user */
        foreach ($this->users as $user) {
            /** @var Model $newGroup */
            $newGroup = $i->createGroup($groupAdmin, $existingMember);

            $i->switchUser($user);

            $data = [
                'membership' => 'write',
            ];

            $i->sendPatchAsJson("/api/user/{$existingMember->getId()}/group/{$newGroup->getId()}", $data);

            if ($user->isSuperuser()) {
                $i->seeResponseCodeIs(200);

                $i->sendGet("/api/user/{$existingMember->getId()}/group/{$newGroup->getId()}", []);
                $i->seeResponseContainsJson(['membership' => 'write']);
            } else {
                $i->seeResponseCodeIs(401);
            }
        }
    }


    /**
     * Remove this user from a group
     * @param ApiTester $i
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testDelete(ApiTester $i)
    {
        $groupAdmin  = $this->users['staff'];
        $groupMember = $this->users['student'];

        /** @var User $user */
        foreach ($this->users as $user) {
            /** @var Model $newGroup */
            $newGroup = $i->createGroup($groupAdmin, $groupMember);

            $i->switchUser($user);

            $i->sendDelete("/api/user/{$groupMember->getId()}/group/{$newGroup->getId()}", []);
            if ($user->isSuperuser()) {
                $i->seeResponseCodeIs(204);
                $i->sendGet("/api/user/{$groupMember->getId()}/group/{$newGroup->getId()}", []);
                $i->seeResponseCodeIs(404);
            } else {
                $i->seeResponseCodeIs(401);
            }
        }
    }
}

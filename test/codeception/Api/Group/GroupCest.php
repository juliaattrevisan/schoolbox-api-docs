<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\Group;

use Tests\Support\ApiTester;
use Schoolbox\User;

/**
 * user@{id}@groupMembership@{groupId}.yaml
 * /usr/share/schoolbox/vendor/bin/codecept run api User/UserGroupMembershipCest
 */
class GroupCest
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
    public function testGetData(ApiTester $i)
    {
        $groupBadminton = $this->users['staff'];

        /** @var User $user */
        foreach ($this->users as $user) {
            $i->switchUser($user);

//            $i->sendGet("/group/getData/{$groupBadminton->getId()}", []);
            $i->sendGet("/group/getData/69", []);
            if ($user->isSuperuser()) {
                $i->seeResponseCodeIs(200);
                $i->seeResponseIsJson();
                $i->seeResponseContains('Badminton');
                $i->seeResponseContains('members');
//                $i->seeResponseContains('Salvadore Dali');
echo "Data:\n";
var_export($i->grabDataFromResponseByJsonPath('$..members'));
echo "\n\n";
            } else {
                $i->seeResponseCodeIs(401);
            }
        }
    }
}

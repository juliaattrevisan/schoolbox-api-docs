<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Test\Api;

use \ApiTester;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * user@{id}.yaml
 * /usr/share/schoolbox/vendor/bin/codecept run api UserViewCest
 *
 */
class UserViewCest
{
    private User $super;
    private User $staff;
    private User $parent;
    private User $student;

    /**
     * @param ApiTester $i
     * @return void
     */
    public function run(ApiTester $i)
    {
        $users = $i->getUsers();

        $this->super = $i->createTestUser('super-' . time(), Role::STAFF, true);
        $this->staff  = $i->createTestUser('staff-' . time(), Role::STAFF, false);
        $this->parent    = $i->createTestUser('parent-' . time(), Role::PARENT, false);
        $this->student     = $i->getUsers()['student'];

        /** @var User $user */
        foreach ($users as $user) {
            $this->testGetUser($i, $user);
        }
    }

    /**
     * @param ApiTester $i
     * @param User      $operator
     * @return void
     */
    private function testGetUser(\ApiTester $i, User $operator)
    {
        //check if current user can view this users getUser api endpoint
        $i->sendGet('/api/user/', ['userId' => $operator->getId()]);
        $i->seeResponseCodeIs(200);

        //check if I can see admin
        $i->sendGet('/api/user/', ['userId' => $this->super->getId()]);
        if ($operator->isSuperuser() || $this->super->getId() === $operator->getId()) {
            //if I am also admin, I should see the user
            $i->seeResponseCodeIs(200);
        } else {
            //else don't show
            $i->seeResponseCodeIs(401);
        }

        //check if user can see other staff
        $i->sendGet('/api/user/', ['userId' => $this->staff->getId()]);
        if ($operator->isSuperuser() || $this->staff->getId() === $operator->getId()) {
            //if I am also admin, I should see the user
            $i->seeResponseCodeIs(200);
        } else {
            //else don't show
            $i->seeResponseCodeIs(401);
        }

        //check if user can see other parent
        $i->sendGet('/api/user/', ['userId' => $this->parent->getId()]);
        if ($operator->isSuperuser() || $this->parent->getId() === $operator->getId()) {
            //if I am also admin, I should see the user
            $i->seeResponseCodeIs(200);
        } else {
            //else don't show
            $i->seeResponseCodeIs(401);
        }

        //check if user can see other student
        $i->sendGet('/api/user/', ['userId' => $this->student->getId()]);
        if ($operator->isSuperuser() || $this->student->getId() === $operator->getId()) {
            //if I am also admin, I should see the user
            $i->seeResponseCodeIs(200);
        } else {
            //else don't show
            $i->seeResponseCodeIs(401);
        }
    }
}

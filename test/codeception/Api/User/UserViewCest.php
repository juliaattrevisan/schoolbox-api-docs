<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\User;

use Tests\Support\ApiTester;
use Schoolbox\User;

/**
 * user@{id}.yaml
 * /usr/share/schoolbox/vendor/bin/codecept run api UserViewCest
 *
 */
class UserViewCest
{
    /**
     * @var User[]
     */
    private array $users;

    /**
     * @param ApiTester $i
     * @return void
     */
    public function run(ApiTester $i)
    {
        $this->users = $i->getUsers();

        /** @var User $user */
        foreach ($this->users as $user) {
            $i->comment("I am user: " . $user->getUsername());
            $this->testGetUser($i, $user);
        }
    }

    /**
     * @param ApiTester $i
     * @param User      $operator
     * @return void
     */
    private function testGetUser(ApiTester $i, User $operator)
    {
        $i->switchUser($operator);

        //check if current user can view this users getUser api endpoint
        $i->sendGet('/api/user/' . $operator->getId());
        $i->seeResponseCodeIs(200);

        //check if I can see admin
        $i->comment("Check if I can see admin");
        $i->sendGet('/api/user/' . $this->users['super']->getId());
        if ($operator->isSuperuser() || ($this->users['super']->getId() === $operator->getId())) {
            //if I am also admin, I should see the user
            $i->seeResponseCodeIs(200);
        } else {
            //else don't show
            $i->seeResponseCodeIs(401);
        }

        //check if user can see other staff
        $i->comment("Check if I can see other staff");
        $i->sendGet('/api/user/' . $this->users['staff']->getId());
        if ($operator->isSuperuser() || ($this->users['staff']->getId() === $operator->getId())) {
            //if I am also admin, I should see the user
            $i->seeResponseCodeIs(200);
        } else {
            //else don't show
            $i->seeResponseCodeIs(401);
        }

        //check if user can see other parent
        $i->comment("Check if I can see other parent");
        $i->sendGet('/api/user/' . $this->users['parent']->getId());
        if ($operator->isSuperuser() || ($this->users['parent']->getId() === $operator->getId())) {
            //if I am also admin, I should see the user
            $i->seeResponseCodeIs(200);
        } else {
            //else don't show
            $i->seeResponseCodeIs(401);
        }

        //check if user can see other student
        $i->comment("Check if I can see other student");
        $i->sendGet('/api/user/' . $this->users['student']->getId());
        if ($operator->isSuperuser() || ($this->users['student']->getId() === $operator->getId())) {
            //if I am also admin, I should see the user
            $i->seeResponseCodeIs(200);
        } else {
            //else don't show
            $i->seeResponseCodeIs(401);
        }

        //check if user can see other Guest
        $i->comment("Check if I can see other Guest");
        $i->sendGet('/api/user/' . $this->users['guest']->getId());
        if ($operator->isSuperuser() || ($this->users['guest']->getId() === $operator->getId())) {
            //if I am also admin, I should see the user
            $i->seeResponseCodeIs(200);
        } else {
            //else don't show
            $i->seeResponseCodeIs(401);
        }
    }
}

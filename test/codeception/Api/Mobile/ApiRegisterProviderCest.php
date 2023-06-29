<?php

/**
 *
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */

declare(strict_types=1);

namespace Tests\Api\Mobile;

use Tests\Support\ApiTester;
use Schoolbox\User;

/**
 * api@register@{provider}.yaml
 * /usr/share/schoolbox/vendor/bin/codecept run api Mobile/ApiRegisterProvider
 *
 */
class ApiRegisterProviderCest
{
    /**
     * @param ApiTester $i
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function run(ApiTester $i)
    {
        $users = $i->getUsers();

        /** @var User $user */
        foreach ($users as $user) {
            $postData = [
                'jwt' => $i->getToken($user->getId()),
                'appId' => 'abc', // mobile_app table
            ];
            $i->sendPost('/api/register/Digistorm', $postData);
            $i->seeResponseCodeIs(400);
            $test2 = $i->grabHttpHeader('COOKIE');
            $test = $i->grabResponse();

            $postData['jwt'] = 'xxx';
            $i->sendPost('/api/register/Digistorm', $postData);
            $i->seeResponseCodeIs(401);
        }
    }
}

<?php

namespace Tests\Support;

use Tests\Support\_generated\ApiTesterActions;

/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause()
 *
 * @SuppressWarnings(PHPMD)
 */
class ApiTester extends \Codeception\Actor
{
    use ApiTesterActions;

    /**
     * Get token
     * @param int $userId
     * @return mixed
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getToken(int $userId)
    {
        $token = $this->getAdminToken();
        $this->amBearerAuthenticated($token);
        $this->haveHttpHeader('Accept', 'application/json');
        $this->sendPost("/user/token/{$userId}");
        return $this->grabDataFromResponseByJsonPath('$.token')[0];
    }

    /**
     * Log in as the user given
     * @param \Schoolbox\User $user
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function switchUser(\Schoolbox\User $user)
    {
        $this->comment("Log in as {$user->getUsername()} ({$user->getId()})");
        $token = $this->getToken($user->getId());
        $this->amBearerAuthenticated($token);
    }
}

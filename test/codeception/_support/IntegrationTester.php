<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/**
 * This actor implements common functionality that is relevant to all integration test suites. Eg:
 * * login prior to Cest tests
 *
 * It should not contain module-specific functionality (that belongs in the relevant Cest)
 *
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
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = null)
 *
 * @SuppressWarnings(PHPMD)
 */
class IntegrationTester extends \Codeception\Actor
{
    use _generated\IntegrationTesterActions;

    /**
     * @todo re-use existing session
     */
    public function loginAdmin(): void
    {
        $loginPayload = new Payload\Authentication\Login();
        $loginPayload->setUsername('alaress');
        $loginPayload->setPassword('alaress');

        $this->stopFollowingRedirects();
        $this->sendRequest($loginPayload);

        $this->seeResponseCodeIs(303);
        $this->seeHttpHeader('location', '/');
    }
}

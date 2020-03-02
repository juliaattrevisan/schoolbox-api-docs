<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace SmokeTests;

use Alaress\Database\Wrapper\Db2\PDO;
use IntegrationTester;
use Payload;

/**
 * Tests:
 * * Can login with username + password
 * * Can request password reset for email or username
 *
 * @noinspection PhpUnused
 */
class LoginCest
{
    /**
     * Hook method to execute prior to each test
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i): void
    {
        $i->stopFollowingRedirects();
    }


    /**
     * Hit /login/ url with POST request to login user.
     *
     * @param IntegrationTester $i
     */
    public function LoginLocalUser(IntegrationTester $i): void
    {
        #GIVEN
        $user     = 'alaress';
        $password = 'alaress';

        #WHEN
        $loginPayload = new Payload\Authentication\Login();
        $loginPayload->setUsername($user);
        $loginPayload->setPassword($password);

        $i->sendRequest($loginPayload);

        #THEN
        $i->seeResponseCodeIs(303);
        $i->sendGET($i->grabHttpHeader('location'));
        $i->seeResponseCodeIs(200);
    }


    /**
     * Hit /passwordReset/createRequest url with POST to request password reset.
     *
     * @param IntegrationTester      $i
     */
    public function LoginForgotPassword(IntegrationTester $i): void
    {
        #GIVEN
        $user_email    = 'alaress@schoolbox.com.au';
        $user_username = 'alaress';

        #WHEN
        $forgotPasswordPayload = new Payload\Authentication\ForgotPassword();
        $forgotPasswordPayload->setEmailOrUsername($user_email);

        $i->amGoingTo('Send request for forgotten password with email');
        $i->sendRequest($forgotPasswordPayload);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', '/passwordReset');

        #WHEN
        $forgotPasswordPayload = new Payload\Authentication\ForgotPassword();
        $forgotPasswordPayload->setEmailOrUsername($user_username);
        $i->amGoingTo('Send request for forgotten password with username');
        $i->sendRequest($forgotPasswordPayload);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', '/passwordReset');
    }

    public function ApiLogin(IntegrationTester $i):void
    {
        #GIVEN
        $user     = 'alaress';
        $password = 'alaress';

        /** @var \Codeception\Module\Db $db */
        $db=$i->grabDBModule();
        $db->haveInDatabase('mobile_app', ['id'=>1, 'app_identifier'=>'auto_test', 'provider'=>'GCM', 'key'=>'anAutoTestKey', 'cert_hash'=>NULL]);
        printf("app_identifier set: " . $db->grabFromDatabase('mobile_app', 'app_identifier', ['id'=>'1']));

        #WHEN
        $loginPayload = new Payload\Authentication\ApiLogin('auto_test');
        $loginPayload->setUsername($user);
        $loginPayload->setPassword($password);

        $i->sendRequest($loginPayload);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', '/');

        #WHEN
        $i->startFollowingRedirects();
        $i->sendRequest($loginPayload);
        $i->seeResponseCodeIs(200);

    }

    public function ApiLoginFailBadPass(IntegrationTester $i):void
    {
        #GIVEN
        $user     = 'alaress';
        $password = 'alar99';

        /** @var \Codeception\Module\Db $db */
        $db=$i->grabDBModule();
        $db->haveInDatabase('mobile_app', ['id'=>1, 'app_identifier'=>'auto_test', 'provider'=>'GCM', 'key'=>'anAutoTestKey', 'cert_hash'=>NULL]);
        printf("app_identifier set: " . $db->grabFromDatabase('mobile_app', 'app_identifier', ['id'=>'1']));

        #WHEN
        $loginPayload = new Payload\Authentication\ApiLogin('auto_test');
        $loginPayload->setUsername($user);
        $loginPayload->setPassword($password);

        $i->sendRequest($loginPayload);

        #THEN
        $i->seeResponseCodeIs(401);
    }

    public function ApiLoginFailWrongApp(IntegrationTester $i):void
    {
        #GIVEN
        $user     = 'alaress';
        $password = 'alaress';

        /** @var \Codeception\Module\Db $db */
        $db=$i->grabDBModule();
        $db->haveInDatabase('mobile_app', ['id'=>1, 'app_identifier'=>'auto_test', 'provider'=>'GCM', 'key'=>'anAutoTestKey', 'cert_hash'=>NULL]);
        printf("app_identifier set: " . $db->grabFromDatabase('mobile_app', 'app_identifier', ['id'=>'1']));

        #WHEN
        $loginPayload = new Payload\Authentication\ApiLogin('auto_app_fail');
        $loginPayload->setUsername($user);
        $loginPayload->setPassword($password);

        $i->sendRequest($loginPayload);

        #THEN
        $i->seeResponseCodeIs(401);
    }

    public function RedirectApiLogin(IntegrationTester $i):void
    {
        #GIVEN
        $user     = 'alaress';
        $password = 'alaress';
        $page     = '/course/list';

        /** @var \Codeception\Module\Db $db */
        $db=$i->grabDBModule();
        $db->haveInDatabase('mobile_app', ['id'=>1, 'app_identifier'=>'auto_test', 'provider'=>'GCM', 'key'=>'anAutoTestKey', 'cert_hash'=>NULL]);
        printf("app_identifier set: " . $db->grabFromDatabase('mobile_app', 'app_identifier', ['id'=>'1']));

        #WHEN
        $loginPayload = new Payload\Authentication\ApiLogin('auto_test');
        $loginPayload->setUsername($user);
        $loginPayload->setPassword($password);
        $loginPayload->setRedirectPageQueryParam($page);

        $i->sendRequest($loginPayload);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', $page);

        #WHEN
        $i->startFollowingRedirects();
        $i->sendRequest($loginPayload);
        $i->seeResponseCodeIs(200);

    }
}

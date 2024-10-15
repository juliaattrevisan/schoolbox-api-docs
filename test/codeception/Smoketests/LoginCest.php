<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/** @noinspection PhpDocMissingThrowsInspection,PhpUnhandledExceptionInspection,PhpUnused */
declare(strict_types=1);

namespace Tests\Smoketests;

use Tests\Support\IntegrationTester;
use Tests\Support\Payload;

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
    public function loginLocalUser(IntegrationTester $i): void
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
    public function loginForgotPassword(IntegrationTester $i): void
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

    public function apiLogin(IntegrationTester $i): void
    {
        #GIVEN
        $user     = 'alaress';
        $password = 'alaress';

        #WHEN
        $loginPayload = new Payload\Authentication\ApiLogin();
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

    public function apiLoginFailBadPass(IntegrationTester $i): void
    {
        #GIVEN
        $user     = 'alaress';
        $password = 'alar99';

        #WHEN
        $loginPayload = new Payload\Authentication\ApiLogin();
        $loginPayload->setUsername($user);
        $loginPayload->setPassword($password);

        $i->sendRequest($loginPayload);

        #THEN
        $i->seeResponseCodeIs(401);
    }

    public function redirectApiLogin(IntegrationTester $i): void
    {
        #GIVEN
        $user     = 'alaress';
        $password = 'alaress';
        $page     = '/course/list';

        #WHEN
        $loginPayload = new Payload\Authentication\ApiLogin();
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

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
}

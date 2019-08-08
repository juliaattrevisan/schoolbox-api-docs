<?php

namespace SmokeTests;

use IntegrationTester;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class LoginCest {
    /**
     * Hit /login/ url with POST request to login user.
     *
     * @param IntegrationTester $i
     *
     * @throws GuzzleException
     */
    public function LoginLocalUser(IntegrationTester $i){

        #GIVEN
        $user='alaress';
        $password='alaress';
        #WHEN
        /** @var Response $response */
        $response = $i->loginUser($user, $password);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected from the login page.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/', $redirect_history[0], 'I expect that we get redirected to the main index page.');
        $i->assertArrayNotHasKey(1, $redirect_history_status, 'I expect there is only one redirect in the history of this request');
    }

    /**
     * Hit /passwordReset/createRequest url with POST to request password reset.
     *
     * @param IntegrationTester $i
     *
     * @throws GuzzleException
     */
    public function LoginForgotPassword(IntegrationTester $i){

        #GIVEN
        $user_email='alaress@schoolbox.com.au';
        $user_username='alaress';
        #WHEN
        /** @var Response $response */
        $response = $i->loginForgotPasswordUsername($user_username);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected from the login page.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/passwordReset', $redirect_history[0], 'I expect that we get redirected to the main index page.');
        $i->assertArrayNotHasKey(1, $redirect_history_status, 'I expect there is only one redirect in the history of this request');

        #WHEN
        /** @var Response $response */
        $response = $i->loginForgotPasswordEmail($user_email);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected from the login page.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/passwordReset', $redirect_history[0], 'I expect that we get redirected to the main index page.');
        $i->assertArrayNotHasKey(1, $redirect_history_status, 'I expect there is only one redirect in the history of this request');
    }
}
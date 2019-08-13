<?php

namespace SmokeTests;

use IntegrationTester;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\GuzzleException;
use Page\Login as LoginPage;
use const GuzzleHelper\VAGRANT_DOMAIN;

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
    public function LoginLocalUser(IntegrationTester $i, LoginPage $loginPage){

        #GIVEN
        $user='alaress';
        $password='alaress';
        $loginRequest = $loginPage->getDefaultLoginRequest();

        #WHEN
        $loginRequest['payload']['form_params']['username']=$user;
        $loginRequest['payload']['form_params']['password']=$password;
        /** @var Response $response */
        $response = $i->sendCurlRequest($loginRequest['method'], $loginRequest['page'], $loginRequest['payload'], true);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/'=>'303'],
            ['-'=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /**
     * Hit /passwordReset/createRequest url with POST to request password reset.
     *
     * @param IntegrationTester $i
     *
     * @throws GuzzleException
     */
    public function LoginForgotPassword(IntegrationTester $i, LoginPage $loginPage){

        #GIVEN
        $user_email='alaress@schoolbox.com.au';
        $user_username='alaress';
        $forgotPasswordRequest = $loginPage->getDefaultForgotPasswordRequest();

        #WHEN
        $forgotPasswordRequest['payload']['form_params']['email']=$user_email;
        /** @var Response $response */
        $response = $i->sendCurlRequest($forgotPasswordRequest['method'], $forgotPasswordRequest['page'], $forgotPasswordRequest['payload'], true);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/passwordReset'=>'302'],
            ['-'=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $forgotPasswordRequest['payload']['form_params']['email']=$user_username;
        /** @var Response $response */
        $response = $i->sendCurlRequest($forgotPasswordRequest['method'], $forgotPasswordRequest['page'], $forgotPasswordRequest['payload'], true);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/passwordReset'=>'302'],
            ['-'=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }
}
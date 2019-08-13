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
        $defaultLoginRequest = $loginPage->getDefaultLoginRequest();
        $defaultLoginRequest['payload']['form_params']['username']=$user;
        $defaultLoginRequest['payload']['form_params']['password']=$password;

        #WHEN
        /** @var Response $response */
        $response = $i->sendCurlRequest($defaultLoginRequest['method'], $defaultLoginRequest['page'], $defaultLoginRequest['payload'], true);
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
        $defaultForgotPasswordRequest = $loginPage->getDefaultForgotPasswordRequest();

        #WHEN
        $defaultForgotPasswordRequest['payload']['form_params']['email']=$user_email;
        /** @var Response $response */
        $response = $i->sendCurlRequest($defaultForgotPasswordRequest['method'], $defaultForgotPasswordRequest['page'], $defaultForgotPasswordRequest['payload'], true);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/passwordReset'=>'302'],
            ['-'=>'200']
        ];
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);


        #WHEN
        $defaultForgotPasswordRequest['payload']['form_params']['email']=$user_username;
        /** @var Response $response */
        $response = $i->sendCurlRequest($defaultForgotPasswordRequest['method'], $defaultForgotPasswordRequest['page'], $defaultForgotPasswordRequest['payload'], true);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/passwordReset'=>'302'],
            ['-'=>'200']
        ];
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }
}
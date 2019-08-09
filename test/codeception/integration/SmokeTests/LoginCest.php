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
    public function LoginForgotPassword(IntegrationTester $i){

        #GIVEN
        $user_email='alaress@schoolbox.com.au';
        $user_username='alaress';
        #WHEN
        /** @var Response $response */
        $response = $i->loginForgotPasswordUsername($user_username);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/passwordReset'=>'302'],
            ['-'=>'200']
        ];
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        /** @var Response $response */
        $response = $i->loginForgotPasswordEmail($user_email);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/passwordReset'=>'302'],
            ['-'=>'200']
        ];
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }
}
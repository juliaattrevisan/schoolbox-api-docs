<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace SmokeTests;

use IntegrationTester;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\GuzzleException;
use Payload;

class LoginCest {
    /**
     * Hit /login/ url with POST request to login user.
     *
     * @param IntegrationTester $i
     *
     * @throws GuzzleException
     */
    public function LoginLocalUser(IntegrationTester $i, Payload\Login $loginPayload){

        #GIVEN
        $user='alaress';
        $password='alaress';


        #WHEN
        $loginPayload->setUsername($user);
        $loginPayload->setPassword($password);

        /** @var Response $response */
        $response = $i->sendCurlRequest($loginPayload, true);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/'=>'303'],
            [IntegrationTester::NO_REDIRECT=>'200']
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
    public function LoginForgotPassword(IntegrationTester $i, Payload\ForgotPassword $forgotPasswordPayload){

        #GIVEN
        $user_email='alaress@schoolbox.com.au';
        $user_username='alaress';


        #WHEN
        $forgotPasswordPayload->setEmailOrUsername($user_email);

        /** @var Response $response */
        $response = $i->sendCurlRequest($forgotPasswordPayload, true, 'Send request for forgotten password with email');
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/passwordReset'=>'302'],
            [IntegrationTester::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $forgotPasswordPayload->setEmailOrUsername($user_username);

        /** @var Response $response */
        $response = $i->sendCurlRequest($forgotPasswordPayload, true, 'Send request for forgotten password with username');
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/passwordReset'=>'302'],
            [IntegrationTester::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }
}

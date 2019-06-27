<?php

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client;

class SmokeInitialCest {

    public function LoginLocalUser(IntegrationTester $i){

        #GIVEN
        $user='alaress';
        $password='alaress';
        #WHEN
        /** @var GuzzleHttp\Psr7\Response $response */
        $response = $i->loginUser($user, $password);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected from the login page.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/', $redirect_history[0], 'I expect that we get redirected to the main index page.');
        $i->assertArrayNotHasKey(1, $redirect_history_status, 'I expect there is only one redirect in the history of this request');
    }

    /**
     *
     */
    public function SmokeNews(IntegrationTester $i){

        #GIVEN
        $title = 'Smoke Test News Title';
        $session_cookies=$i->loginAdminGetToken();
        #WHEN
        /** @var GuzzleHttp\Psr7\Response $response */
        $response = $i->draftNews($session_cookies, $title);
        print_r($response->getHeaders());
        #THEN
        $i->assertEquals(303, $response->getStatusCode(), 'I expect that we get a 303 after a draft is submitted.');
    }
}

//PHPSESSID=dua5o1pn0jmmhp4g9iq9l09mnk; expires=Tue, 25-Jun-2019 00:14:41 GMT; Max-Age=3600; path=/; HttpOnly
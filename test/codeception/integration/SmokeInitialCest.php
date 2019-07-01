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
        #The regex matches: https://vagrant,dev.echoolbox.cloud/news/<atricle_id>?returnUrl=Lw%253D%253D
        $return_page_url_regex_pattern='/https:\/\/vagrant\.dev\.schoolbox\.cloud\/news\/\d+\?returnUrl=Lw%253D%253D/';
        $capture_new_news_id='/news\/(\d+)\?returnUrl/';
        $title = 'Smoke Test News Title Draft';
        $i->loginAdmin();
        #WHEN
        /** @var GuzzleHttp\Psr7\Response $response */
        $response = $i->draftNews($title);
        $i->comment('Created a draft');
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected from the create news page when draft is auto submitted.');
        $i->assertRegExp($return_page_url_regex_pattern, $redirect_history[0], 'I expect that we get redirected to the main index page.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        $title = 'Smoke Test News Title Moderate';
        $response = $i->draftNews($title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        #WHEN
        preg_match($capture_new_news_id, $redirect_history[0],$news_id_matches);
        $response = $i->createNewsToModerate($title, $news_id_matches[1]);
        $i->comment('Pushed draft to moderation');
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I publish the draft.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/news/'.$news_id_matches[1], $redirect_history[0], 'I expect that we get redirected to the news article we just published.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        $title = 'Smoke Test News Title Publish Form Path';
        $response = $i->draftNews($title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        #WHEN
        preg_match($capture_new_news_id, $redirect_history[0],$news_id_matches);
        $response = $i->publishNewsForm($title, $news_id_matches[1]);
        $i->comment('Publish Draft through update');
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I publish the draft.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/news/'.$news_id_matches[1], $redirect_history[0], 'I expect that we get redirected to the news article we just published.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        $title = 'Smoke Test News Title Publish ID article Path';
        $response = $i->draftNews($title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        #WHEN
        preg_match($capture_new_news_id, $redirect_history[0],$news_id_matches);
        $response = $i->publishNewsByID($news_id_matches[1]);
        $i->comment('Publish Draft with the ID');
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I publish the draft.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/news/'.$news_id_matches[1], $redirect_history[0], 'I expect that we get redirected to the news article we just published.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        $title = 'Smoke Test News Title Reject By ID';
        $response = $i->draftNews($title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        #WHEN
        preg_match($capture_new_news_id, $redirect_history[0],$news_id_matches);

        $i->createNewsToModerate($title, $news_id_matches[1]);
        $response = $i->rejectNewsByID($news_id_matches[1]);
        $i->comment('Rejected a Moderation waiting News Article');
        #THEN
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        $title = 'Smoke Test News Title Delete, I should not exist';
        $response = $i->draftNews($title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        #WHEN
        preg_match($capture_new_news_id, $redirect_history[0],$news_id_matches);
        $i->publishNewsByID($news_id_matches[1]);
        $response = $i->deleteNewsByID($news_id_matches[1]);
        $i->comment('Deleted a News Article');
        #THEN
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');
    }

    /**
     *
     */
    public function SmokeAssessment(IntegrationTester $i){
        $i->loginAdmin();

        $title = 'Smoke Test Assignment Title';
        $capture_new_assignment_id='/news\/(\d+)\?returnUrl/';
        $response = $i->createAssessemntHomepage($title, 552);

        print_r($response);
    }


}

//PHPSESSID=dua5o1pn0jmmhp4g9iq9l09mnk; expires=Tue, 25-Jun-2019 00:14:41 GMT; Max-Age=3600; path=/; HttpOnly
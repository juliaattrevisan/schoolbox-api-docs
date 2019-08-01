<?php

use GuzzleHttp\Psr7\Response;

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class NewsCest {
    /**
     * Smoke test news post forms include following actions:
     * Draft news
     * Publish news to moderate section
     * Publish news instatly without moderation
     * Publish news from moderation
     * Reject news that was to be moderated
     * Save news for later
     * Create and delete news artivle
     *
     * Each of these in the future itteration should be split into sepperate test functions.
     *
     * @param IntegrationTester $i
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function SmokeNewsPOST(IntegrationTester $i){

        #GIVEN
        $i->loginAdmin();
        #The regex matches: https://vagrant,dev.echoolbox.cloud/news/<atricle_id>?returnUrl=Lw%253D%253D
        $return_page_url_regex_pattern='/https:\/\/vagrant\.dev\.schoolbox\.cloud\/news\/\d+\?returnUrl=Lw%253D%253D/';
        $capture_new_news_id='/news\/(\d+)\?returnUrl/';
        $title = 'Smoke Test News Title Draft';
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
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I publish the draft to be moderated.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/news/'.$news_id_matches[1], $redirect_history[0], 'I expect that we get redirected to the news article we just pushed to moderation.');
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
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I publish the draft directly.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/news/'.$news_id_matches[1], $redirect_history[0], 'I expect that we get redirected to the news article we just published the draft directly.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        $title = 'Smoke Test News Title Publish Moderate Path';
        $response = $i->draftNews($title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        #WHEN
        preg_match($capture_new_news_id, $redirect_history[0],$news_id_matches);
        $response = $i->publishModeratedNewsByID($news_id_matches[1]);
        $i->comment('Publish Draft with the ID');
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I publish via moderate.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/news/'.$news_id_matches[1], $redirect_history[0], 'I expect that we get redirected to the news article we just published via moderate.');
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
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get 200 after rejection of article.');

        $title = 'Smoke Test News Title Save for later';
        $response = $i->draftNews($title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        #WHEN
        preg_match($capture_new_news_id, $redirect_history[0],$news_id_matches);
        $i->publishModeratedNewsByID($news_id_matches[1]);
        $response = $i->saveNewsByIDforLater($news_id_matches[1]);
        $i->comment('Saved news article for later');
        #THEN
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after save news for later.');

        $title = 'Smoke Test News Title Delete, I should not exist';
        $response = $i->draftNews($title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        #WHEN
        preg_match($capture_new_news_id, $redirect_history[0],$news_id_matches);
        $i->publishModeratedNewsByID($news_id_matches[1]);
        $response = $i->deleteNewsByID($news_id_matches[1]);
        $i->comment('Deleted a News Article');
        #THEN
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after delete news executed on "'.$title.'".');
    }
}
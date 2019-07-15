<?php

use GuzzleHttp\Psr7\Response;

/**
 * @author    Alexamder Tsuru <alexander.simonov@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

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


    public function LoginForgotPassword(IntegrationTester $i){

        #GIVEN
        $user_email='alaress@schoolbox.com.au';
        $user_username='alaress';
        #WHEN
        /** @var GuzzleHttp\Psr7\Response $response */
        $response = $i->loginForgotPasswordUsername($user_username);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected from the login page.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/passwordReset', $redirect_history[0], 'I expect that we get redirected to the main index page.');
        $i->assertArrayNotHasKey(1, $redirect_history_status, 'I expect there is only one redirect in the history of this request');

        #WHEN
        /** @var GuzzleHttp\Psr7\Response $response */
        $response = $i->loginForgotPasswordEmail($user_email);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected from the login page.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/passwordReset', $redirect_history[0], 'I expect that we get redirected to the main index page.');
        $i->assertArrayNotHasKey(1, $redirect_history_status, 'I expect there is only one redirect in the history of this request');
    }

    /**
     *
     */
    public function SmokeNewsPOST(IntegrationTester $i){

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

    /**
     *
     */
    public function SmokeAssessmentPOST(IntegrationTester $i){
        #GIVEN
        $homepage_title='Testing Home Page for Assessment';
        $i->loginAdmin(); #we logged in as alaress
        $i->createHomepage($homepage_title);
        $homePageId=$i->getFirstHomepageIdByTitle($homepage_title);
        $i->addDefaultUsersToHomepage($homePageId);
        $student = $i->getUser('drhodes');
        $admin = $i->getUser('alaress');
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId;

        #WHEN
        $response = $i->createAssessemntOnHomepage($assessment_title,$homePageId);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');

        #THEN
        $i->comment('Created homepage and added assessment to it');
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after I create an assessment.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/homepage/'.$homePageId, $redirect_history[0], 'T expect we get redirected to the homepage our assessment is on.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId.' Edit it';
        $i->createAssessemntOnHomepage($assessment_title, $homePageId);
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title, $homePageId);
        $determine_mark_response = $i->determineMarkInvalidation($homePageId, $assessmentPageId, $assessment_title);
        $update_response = $i->updateAssessemntOnHomepage($homePageId, $assessmentPageId, $assessment_title);

        #THEN
        $i->comment('Updated Assessment');
        $i->assertEquals(200, $determine_mark_response->getStatusCode(), 'I expect that we get a 200 after mark validation on assessment update.');
        $i->assertEquals(200, $update_response->getStatusCode(), 'I expect that we get a 200 after updating assessment.');

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId.' With Rubric';
        $response = $i->createAssessementForHomepageWithRubric($assessment_title,$homePageId);

        #THEN
        $i->comment('Create Assessment with rubric for a homepage');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after creating an assessment with rubric.');

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId;
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);
        $response = $i->submitAssessment($assessmentPageId, $student->getId());

        #THEN
        $i->comment('Submit assessment on behalf of student');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after submitting assessment on behalf of student.');

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId.' With Rubric';
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);
        $i->submitAssessment($assessmentPageId, $student->getId());

        #THEN
        $response = $i->markAssessmentWithRubric($assessmentPageId, $admin->getId(), $student->getId());
        $i->comment('Mark assessment with rubric');

        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after marking assessement with rubric');

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId;
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);
        $response = $i->markAssessment($assessmentPageId, $admin->getId(), $student->getId());

        #THEN
        $i->comment('Mark normal assessment');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after marking assesssment.');

        ##To Do - Submit reflection on assessment.


    }


}

//PHPSESSID=dua5o1pn0jmmhp4g9iq9l09mnk; expires=Tue, 25-Jun-2019 00:14:41 GMT; Max-Age=3600; path=/; HttpOnly
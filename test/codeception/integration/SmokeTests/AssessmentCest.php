<?php

use GuzzleHttp\Psr7\Response;

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class AssessmentCest {
    /**
     * Smoke test assessment post forms include following actions:
     * Add assessment to created homepage
     * Update assesssment
     * Create assessment with rubric
     * Submit assessment on behalf of student
     * Mark assessment with rubric
     * Mark normal assessment
     *
     * Each of these in the future itteration should be split into sepperate test functions.
     *
     * @param IntegrationTester $i
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function SmokeAssessmentPOST(IntegrationTester $i){
        #GIVEN
        $i->loginAdmin(); #we logged in as alaress
        $homepage_title='Testing Home Page for Assessment';
        $i->createHomepage($homepage_title);
        $homePageId=$i->getFirstHomepageIdByTitle($homepage_title);
        $i->addDefaultUsersToHomepage($homePageId);
        $student = $i->getUser('drhodes');
        $admin = $i->getUser('alaress');
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId;

        #WHEN
        $response = $i->createAssessmentOnHomepage($assessment_title,$homePageId);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');

        #THEN
        $i->comment('Created homepage and added assessment to it');
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after I create an assessment.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/homepage/'.$homePageId, $redirect_history[0], 'I expect we get redirected to the homepage our assessment is on.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId.' Edit it';
        $i->createAssessmentOnHomepage($assessment_title, $homePageId);
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title, $homePageId);
        $determine_mark_response = $i->determineMarkInvalidation($homePageId, $assessmentPageId, $assessment_title);
        $update_response = $i->updateAssessmentOnHomepage($homePageId, $assessmentPageId, $assessment_title);

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
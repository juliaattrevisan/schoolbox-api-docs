<?php

namespace SmokeTests;

use GuzzleHelper\GuzzleConstants;
use GuzzleHttp\Exception\GuzzleException;
use IntegrationTester;
use GuzzleHttp\Psr7\Response;
use Schoolbox\External\Exception;
use Schoolbox\Mapper\Exception\Deleted;

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
     * @throws GuzzleException
     * @throws \Schoolbox\Mapper\Exception
     * @throws Deleted
     * @throws Exception
     */
    public function SmokeAssessmentPOST(IntegrationTester $i):void
    {
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
        /** @var Response $response */
        $response = $i->createAssessmentOnHomepage($assessment_title,$homePageId);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/homepage/'.$homePageId=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->comment('Created homepage and added assessment to it');
        $i->checkCurlReturn($expectedUrlsReturned, $response);



        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId.' Edit it';
        $i->createAssessmentOnHomepage($assessment_title, $homePageId);
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);
        $determine_mark_response = $i->determineMarkInvalidation($homePageId, $assessmentPageId, $assessment_title);
        $update_response = $i->updateAssessmentOnHomepage($homePageId, $assessmentPageId, $assessment_title);
        $expectedUrlsReturned= [[GuzzleConstants::NO_REDIRECT=>'200']];

        #THEN
        $i->comment('Updated Assessment');
        $i->checkCurlReturn($expectedUrlsReturned, $determine_mark_response);
        $i->checkCurlReturn($expectedUrlsReturned, $update_response);

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId.' With Rubric';
        $response = $i->createAssessementForHomepageWithRubric($assessment_title,$homePageId);

        #THEN
        $i->comment('Create Assessment with rubric for a homepage');
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId;
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);
        $response = $i->submitAssessment($assessmentPageId, $student->getId());

        #THEN
        $i->comment('Submit assessment on behalf of student');
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId.' With Rubric';
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);
        $i->submitAssessment($assessmentPageId, $student->getId());

        #THEN
        $response = $i->markAssessmentWithRubric($assessmentPageId, $admin->getId(), $student->getId());
        $i->comment('Mark assessment with rubric');

        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $assessment_title='Testing Assessment On Homepage ID '.$homePageId;
        $assessmentPageId=$i->getFirstAssessmentIdByTitle($assessment_title);
        $response = $i->markAssessment($assessmentPageId, $admin->getId(), $student->getId());

        #THEN
        $i->comment('Mark normal assessment');
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        ##To Do - Submit reflection on assessment.

    }
}
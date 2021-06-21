<?php

declare(strict_types=1);

namespace SmokeTests;

use IntegrationTester;
use Payload;


class LearningMomentsCest
{

    /**
     * Push a login payload to Schoolbox each test to ensure session is active and logged in.
     *
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i): void
    {
        $i->loginAdmin();
        $i->stopFollowingRedirects();
    }


    /** Create a learning moment by writing something and submitting it
     * check if response code is 200
     *
     * @param IntegrationTester    $i
     */
    public function createLearningMomentText(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed = $i->grabUser('jreed');
        $type= 'text';
        $timestamp = time();
        $body     = 'Learning Moment Text: Automation API Smoke Test' . $timestamp;

        #WHEN
        $learningMomentPayload = new Payload\LearningMoment\Create($student_jreed->getId(),$type, $body);
        $i->amGoingTo('Send request to create a learning moment');
        $i->sendRequest($learningMomentPayload);

        #THEN
        $i->seeResponseCodeIs(200);

    }

    /** Update a text learning moment submitting it
     * check if response code is 200
     *
     * @param IntegrationTester    $i
     */
    public function modifyLearningMomentText(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed = $i->grabUser('jreed');
        $type= 'text';
        $timestamp = time();
        $body     = 'Learning Moment Text: Automation API Smoke Test' . $timestamp;
        $learningMomentPayload = new Payload\LearningMoment\Create($student_jreed->getId(),$type, $body);
        $i->amGoingTo('Send request to create a learning moment');
        $i->sendRequest($learningMomentPayload);
        $i->seeResponseCodeIs(200);

        $evidence = $i->grabLearningMomentByBody($body);

        #WHEN
        $body     = 'Learning Moment Text: Automation API Smoke Test - getting this text updated' . $timestamp;
        $type= 'text';
        $learningMomentPayloadModify = new Payload\LearningMoment\Modify($evidence->getId(), $type,  $body);
        $i->amGoingTo('Send request to update a learning moment');
        $i->sendRequest($learningMomentPayloadModify);
        $i->seeResponseCodeIs(200);

    }



}

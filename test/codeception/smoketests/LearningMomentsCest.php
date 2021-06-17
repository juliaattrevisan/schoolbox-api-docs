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


    /** Create draft news using curl request
     *
     * @param IntegrationTester    $i
     */
    public function createLearningMomentText(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed = $i->grabUser('jreed');
        $type= 'text';
        $timestamp = time();
        $body     = 'Smoke Test Learning Moment type text ' . $timestamp;

        #WHEN
        $learningMomentPayload = new Payload\LearningMoment\Create($student_jreed->getId(),$type);
        $i->amGoingTo('Send request to create a learning moment');
        $i->sendRequest($learningMomentPayload);

        #THEN
        $i->seeResponseCodeIs(200);

    }
}

<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace SmokeTests;

use IntegrationTester;
use Payload\Admin\LMS\LessonPlanTemplate;

class LessonPlanCest
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

    /**
     * Creates an assessment using a curl payload
     *
     * Fires off creation of a group homepage folder.
     *
     * @param IntegrationTester $i
     */
    public function createLessonPlanTemplate(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();

        $lessons = array(
            array(1, 'Field 1' . $timestamp, 'on', 'Body ' . $timestamp),
            array(2, 'Field 2' . $timestamp, 'off', 'Body 2 ' . $timestamp)
        );

        $lessonPlanTemplateCreate = new LessonPlanTemplate(
            'Lesson Plan Template ' . $timestamp,
            $lessons
        );

        #WHEN
        $i->sendRequest($lessonPlanTemplateCreate);

        #THEN
        $i->seeResponseCodeIs(303);
    }
}

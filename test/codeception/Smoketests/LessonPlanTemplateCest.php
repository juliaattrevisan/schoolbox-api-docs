<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Smoketests;

use Tests\Support\IntegrationTester;
use Tests\Support\Payload\Admin\LMS\LessonPlanTemplate\CreateLessonPlanTemplate;
use Tests\Support\Payload\Admin\LMS\LessonPlanTemplate\DeleteLessonPlanTemplate;
use Tests\Support\Payload\Admin\LMS\LessonPlanTemplate\EnableDisableLessonPlanTemplate;
use Tests\Support\Payload\Admin\LMS\LessonPlanTemplate\ModifyLessonPlanTemplate;

class LessonPlanTemplateCest
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
     * Creates a lesson plan template using a curl payload
     *
     *
     * @param IntegrationTester $i
     */
    public function createLessonPlanTemplate(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();
        $lessonPlanTemplateTitle = 'Lesson Plan Template ' . $timestamp;
        $timestamp  = time();

        $lessons = array(
            array(1, 'Field 1 ' . $timestamp, 'on', 'Body ' . $timestamp),
            array(2, 'Field 2 ' . $timestamp, 'on', 'Body 2 ' . $timestamp),
            array(3, 'Field 3 ' . $timestamp, 'off', 'Body 3 ' . $timestamp)
        );

        $lessonPlanTemplateCreate = new CreateLessonPlanTemplate(
            $lessonPlanTemplateTitle,
            $lessons
        );

        #WHEN
        $i->amGoingTo('Send request to create a lesson plan template');
        $i->sendRequest($lessonPlanTemplateCreate);
        $lessonPlanName = $i->grabLessonPlanTemplateByTitle($lessonPlanTemplateTitle);

        #THEN
        $i->seeResponseCodeIs(303);

        #WHEN
        $disableEnableLessonPlanTemplate = new EnableDisableLessonPlanTemplate($lessonPlanName->getId());
        $i->amGoingTo('Send request to disable a lesson plan template');
        $i->sendRequest($disableEnableLessonPlanTemplate);

        #THEN
        $i->seeResponseCodeIs(200);
    }

    /**
     * Update a lesson plan template using a curl payload
     *
     *
     * @param IntegrationTester $i
     */
    public function updateLessonPlanTemplate(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();
        $lessonPlanTemplateTitle = 'Lesson Plan Template to be updated ' . $timestamp;
        $lessonPlanFieldName1 = 'Field 1 to be updated' . $timestamp;
        $lessonPlanFieldName2 = 'Field 2 to be updated' . $timestamp;
        $lessonPlanFieldName3 = 'Field 3 to be updated' . $timestamp;

        $lessons = array(
            array(1, $lessonPlanFieldName1, 'off', 'Body ' . $timestamp),
            array(2, $lessonPlanFieldName2, 'off', 'Body 2 ' . $timestamp),
            array(3, $lessonPlanFieldName3, 'off', 'Body 3 ' . $timestamp)
        );

        $lessonPlanTemplateCreate = new CreateLessonPlanTemplate(
            $lessonPlanTemplateTitle,
            $lessons
        );

        $i->amGoingTo('Send request to create a lesson plan template');
        $i->sendRequest($lessonPlanTemplateCreate);
        $i->seeResponseCodeIs(303);
        $lessonPlanName = $i->grabLessonPlanTemplateByTitle($lessonPlanTemplateTitle);
        $lessonPlanFieldName1 = $i->grabLessonPlanTemplateFieldByTitle($lessonPlanFieldName1);
        $lessonPlanFieldName2 = $i->grabLessonPlanTemplateFieldByTitle($lessonPlanFieldName2);
        $lessonPlanTemplateId = $lessonPlanName->getId();
        $lessonPlanFieldName1Id = $lessonPlanFieldName1->getId();
        $lessonPlanFieldName2Id = $lessonPlanFieldName2->getId();

        #WHEN

        $lessonPlanTemplateNewTitle = 'Lesson Plan Template to be updated test ' . $timestamp;

        $lessons = array(
            array($lessonPlanFieldName1Id, 'Field 1 updated ' . $timestamp, 'on', 'Body updated ' . $timestamp),
            array($lessonPlanFieldName2Id, 'Field 2 updated ' . $timestamp, 'on', 'Body 2 updated ' . $timestamp),
        );

        $lessonPlanTemplateUpdate = new ModifyLessonPlanTemplate(
            $lessonPlanTemplateNewTitle,
            $lessonPlanTemplateId,
            $lessons
        );

        #WHEN
        $i->amGoingTo('Send request to update a lesson plan template');
        $i->sendRequest($lessonPlanTemplateUpdate);

        #THEN
        $i->seeResponseCodeIs(303);
    }

    /**
     * Delete a lesson plan template using a curl payload
     *
     *
     * @param IntegrationTester $i
     * @throws \JsonException
     */
    public function deleteLessonPlanTemplate(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();
        $lessonPlanTemplateTitle = 'Lesson Plan Template to be deleted ' . $timestamp;

        $lessons = array(
            array(1, 'Field 1 ' . $timestamp, 'on', 'Body ' . $timestamp),
            array(2, 'Field 2 ' . $timestamp, 'on', 'Body 2 ' . $timestamp),
            array(3, 'Field 3 ' . $timestamp, 'off', 'Body 3 ' . $timestamp)
        );

        $lessonPlanTemplateCreate = new CreateLessonPlanTemplate(
            $lessonPlanTemplateTitle,
            $lessons
        );
        $i->amGoingTo('Send request to create a lesson plan template');
        $i->sendRequest($lessonPlanTemplateCreate);
        $i->seeResponseCodeIs(303);
        $lessonPlanName = $i->grabLessonPlanTemplateByTitle($lessonPlanTemplateTitle);

        #WHEN
        $lessonPlanTemplateDelete = new DeleteLessonPlanTemplate($lessonPlanName->getId());
        $i->amGoingTo('Send request to delete a lesson plan template');
        $i->sendRequest($lessonPlanTemplateDelete);

      #THEN
        $i->seeResponseCodeIs(200);
    }
}

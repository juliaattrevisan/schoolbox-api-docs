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
     * @param IntegrationTester $i
     */
    public function createLearningMomentText(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed = $i->grabUser('jreed');
        $type          = 'text';
        $timestamp     = time();
        $body          = 'Learning Moment Text: Automation API Smoke Test' . $timestamp;
        $file          = null;

        #WHEN
        $learningMomentPayload = new Payload\LearningMoment\Create($student_jreed->getId(), $type, $body, $file);
        $i->amGoingTo('Send request to create a learning moment');
        $i->sendRequest($learningMomentPayload);

        #THEN
        $i->seeResponseCodeIs(200);
    }

    /** Create a learning moment by uploading a file and submitting it
     * check if response code is 200
     *
     * @param IntegrationTester $i
     */
    public function createLearningMomentUpload(IntegrationTester $i): void
    {
        #GIVEN
        $student_ereed = $i->grabUser('ereed');
        $type          = 'upload';
        $timestamp     = time();
        $body          = 'Learning Moment Upload File: Automation API Smoke Test' . $timestamp;
        $image_name          = "homepageUploadTestDragDrop{$timestamp}.png";
        $image_location      = '/UploadTestFiles/fish2.png';

        $storageUpload = new Payload\Resource\File\StorageUpload('upload', $image_name, $image_location);
        $i->amGoingTo('Upload image to the Badge');
        $i->sendRequest($storageUpload);
        $i->seeResponseCodeIs(200);
        $json_reply = json_decode($i->grabResponse(), true);
        $file = ($json_reply['hash'] . ":fish2.png");

        #WHEN
        $learningMomentPayload = new Payload\LearningMoment\Create($student_ereed->getId(), $type, $body, $file);
        $i->amGoingTo('Send request to create a learning moment');
        $i->sendRequest($learningMomentPayload);

        #THEN
        $i->seeResponseCodeIs(200);
    }
    /** Update a text learning moment submitting it
     * check if response code is 200
     *
     * @param IntegrationTester $i
     */
    public function modifyLearningMomentText(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed         = $i->grabUser('jreed');
        $type                  = 'text';
        $timestamp             = time();
        $body                  = 'Learning Moment Text: Automation API Smoke Test' . $timestamp;
        $file                  = null;
        $learningMomentPayload = new Payload\LearningMoment\Create($student_jreed->getId(), $type, $body, $file);
        $i->amGoingTo('Send request to create a learning moment');
        $i->sendRequest($learningMomentPayload);
        $i->seeResponseCodeIs(200);

        $evidence = $i->grabLearningMomentByBody($body);

        #WHEN
        $body                        = 'Learning Moment Text: Automation API Smoke Test - getting this text updated' . $timestamp;
        $type                        = 'text';
        $learningMomentPayloadModify = new Payload\LearningMoment\Modify($evidence->getId(), $type, $body, $file);
        $i->amGoingTo('Send request to update a learning moment');
        $i->sendRequest($learningMomentPayloadModify);

        #THEN
        $i->seeResponseCodeIs(200);
    }

    /** Update the text and file of a learning moment and submitting it
     * check if response code is 200
     *
     * @param IntegrationTester $i
     */
    public function modifyLearningMomentUpload(IntegrationTester $i): void
    {
        #GIVEN
        $student_ereed = $i->grabUser('ereed');
        $type          = 'upload';
        $timestamp     = time();
        $body          = 'Learning Moment Upload File: Automation API Smoke Test' . $timestamp;
        $image_name          = "homepageUploadTestDragDrop{$timestamp}.png";
        $image_location      = '/UploadTestFiles/fish2.png';
        $storageUpload = new Payload\Resource\File\StorageUpload('upload', $image_name, $image_location);
        $i->amGoingTo('Upload image to the Badge');
        $i->sendRequest($storageUpload);
        $i->seeResponseCodeIs(200);
        $json_reply = json_decode($i->grabResponse(), true);
        $file = ($json_reply['hash'] . ":fish2.png");
        $learningMomentPayload = new Payload\LearningMoment\Create($student_ereed->getId(), $type, $body, $file);
        $i->amGoingTo('Send request to create a learning moment');
        $i->sendRequest($learningMomentPayload);
        $i->seeResponseCodeIs(200);
        $evidence = $i->grabLearningMomentByBody($body);

        #WHEN
        $body                        = 'Learning Moment Upload File: Automation API Smoke Test - getting this text and image updated' . $timestamp;
        $type                        = 'upload';
        $image_location      = '/UploadTestFiles/cat.png';
        $storageUpload = new Payload\Resource\File\StorageUpload('upload', $image_name, $image_location);
        $i->amGoingTo('Upload image to the Badge');
        $i->sendRequest($storageUpload);
        $i->seeResponseCodeIs(200);
        $json_reply = json_decode($i->grabResponse(), true);
        $file = ($json_reply['hash'] . ":cat.png");
        $learningMomentPayloadModify = new Payload\LearningMoment\Modify($evidence->getId(), $type, $body, $file);
        $i->amGoingTo('Send request to update a learning moment');
        $i->sendRequest($learningMomentPayloadModify);

        #THEN
        $i->seeResponseCodeIs(200);
    }


    /** Delete a learning moment
     * check if response code is 200
     *
     * @param IntegrationTester $i
     */
    public function deleteLearningMomentText(IntegrationTester $i): void
    {
        #GIVEN
        $student_jreed         = $i->grabUser('jreed');
        $type                  = 'text';
        $timestamp             = time();
        $body                  = 'Learning Moment Text To Be DELETED: Automation API Smoke Test' . $timestamp;
        $file                  =  null;
        $learningMomentPayload = new Payload\LearningMoment\Create($student_jreed->getId(), $type, $body, $file);
        $i->amGoingTo('Send request to create a learning moment');
        $i->sendRequest($learningMomentPayload);
        $i->seeResponseCodeIs(200);

        $evidence = $i->grabLearningMomentByBody($body);

        #WHEN
        $learningMomentPayloadDelete = new Payload\LearningMoment\Delete($student_jreed->getId(), $evidence->getId());
        $i->amGoingTo('Send request to delete a learning moment');
        $i->sendRequest($learningMomentPayloadDelete);

        #THEN
        $i->seeResponseCodeIs(200);
    }
}

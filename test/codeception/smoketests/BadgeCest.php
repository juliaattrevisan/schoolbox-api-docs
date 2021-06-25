<?php

declare(strict_types=1);

namespace SmokeTests;

use IntegrationTester;
use Payload;

class BadgeCest
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
        $type= 'text';
        $badgeavatarselector = 'image';
        $category = 1;
        $timestamp = time();
        $body     = 'Badge Description: Automation API Smoke Test' . $timestamp;
        $name = 'Badge Name Automation API Smoke Test'. $timestamp;
        $image_name = "homepageUploadTestDragDrop{$timestamp}.png";
        $image_location = '/UploadTestFiles/fish2.png';


        $storageUpload = new Payload\Resource\File\StorageUpload('upload', $image_name, $image_location);
        $i->amGoingTo('Upload image to storage');
        $i->sendRequest($storageUpload);
        $i->seeResponseCodeIs(200);
        $json_reply = json_decode($i->grabResponse(), true);

        $badgePayload = new Payload\Badge\Create($badgeavatarselector,$category,$body,$name);
        $badgePayload->setFormParam('image',$json_reply['hash'] . ":fish2.png");

        #WHEN

        $i->amGoingTo('Send request to create a learning moment');
        $i->sendRequest($badgePayload);

        #THEN
        $i->seeResponseCodeIs(200);

    }

}

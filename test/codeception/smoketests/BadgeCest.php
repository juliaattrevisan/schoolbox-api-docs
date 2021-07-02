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


    /** Create a Badge
     * check if response code is 200
     *
     * @param IntegrationTester $i
     */
    public function createBadge(IntegrationTester $i): void
    {
        #GIVEN
        $badgeavatarselector = 'image';
        $category            = 1;
        $timestamp           = time();
        $body                = 'Badge Description: Automation API Smoke Test' . $timestamp;
        $name                = 'Badge Name Automation API Smoke Test' . $timestamp;
        $image_name          = "homepageUploadTestDragDrop{$timestamp}.png";
        $image_location      = '/UploadTestFiles/fish2.png';


        $storageUpload = new Payload\Resource\File\StorageUpload('upload', $image_name, $image_location);
        $i->amGoingTo('Upload image to the Badge');
        $i->sendRequest($storageUpload);
        $i->seeResponseCodeIs(200);
        $json_reply = json_decode($i->grabResponse(), true);

        $badgePayload = new Payload\Badge\Create($badgeavatarselector, $category, $body, $name, false);
        $badgePayload->setFormParam('image', $json_reply['hash'] . ":fish2.png");

        #WHEN

        $i->amGoingTo('Send request to create a badge');
        $i->sendRequest($badgePayload);

        #THEN
        $i->seeResponseCodeIs(200);
    }

    /** Modify a Badge
     * check if response code is 200
     *
     * @param IntegrationTester $i
     */
    public function modifyBadge(IntegrationTester $i): void
    {
        /** creating a Badge
         */
        #GIVEN
        $badgeavatarselector = 'image';
        $category            = 1;
        $timestamp           = time();
        $body                = 'Badge Description: Automation API Smoke Test' . $timestamp;
        $name                = 'Badge Name Automation API Smoke Test' . $timestamp;
        $image_name          = "homepageUploadTestDragDrop{$timestamp}.png";
        $image_location      = '/UploadTestFiles/fish2.png';

        $storageUpload = new Payload\Resource\File\StorageUpload('upload', $image_name, $image_location);
        $i->amGoingTo('Upload image to the badge');
        $i->sendRequest($storageUpload);
        $i->seeResponseCodeIs(200);
        $json_reply = json_decode($i->grabResponse(), true);

        $badgePayload = new Payload\Badge\Create($badgeavatarselector, $category, $body, $name, false);
        $badgePayload->setFormParam('image', $json_reply['hash'] . ":fish2.png");
        $i->amGoingTo('Send request to create a badge');
        $i->sendRequest($badgePayload);
        $i->seeResponseCodeIs(200);
        $badge = $i->grabFirstBadgeByTitle($name);

        /** modifying the Badge
         */
        #WHEN
        $name               = 'Badge Name Automation API Smoke Test updated with allow as a goal on' . $timestamp;
        $badgeModifyPayload = new Payload\Badge\Modify($badge->getId(), $badgeavatarselector, $category, $body, $name, true);
        $badgeModifyPayload->setFormParam('image', $json_reply['hash']);
        $i->amGoingTo('Send request to update a badge');
        $i->sendRequest($badgeModifyPayload);

        #THEN
        $i->seeResponseCodeIs(200);
    }

    /** Delete a Badge
     * check if response code is 200
     *
     * @param IntegrationTester $i
     */
    public function deleteBadge(IntegrationTester $i): void
    {
        /** creating a Badge
         */
        #GIVEN
        $badgeavatarselector = 'image';
        $category            = 1;
        $timestamp           = time();
        $body                = 'Badge Description: Automation API Smoke Test to be deleted' . $timestamp;
        $name                = 'Badge Name Automation API Smoke Test to be deleted' . $timestamp;
        $image_name          = "homepageUploadTestDragDrop{$timestamp}.png";
        $image_location      = '/UploadTestFiles/fish2.png';

        $storageUpload = new Payload\Resource\File\StorageUpload('upload', $image_name, $image_location);
        $i->amGoingTo('Upload image to the badge');
        $i->sendRequest($storageUpload);
        $i->seeResponseCodeIs(200);
        $json_reply = json_decode($i->grabResponse(), true);

        $badgePayload = new Payload\Badge\Create($badgeavatarselector, $category, $body, $name, false);
        $badgePayload->setFormParam('image', $json_reply['hash'] . ":fish2.png");
        $i->amGoingTo('Send request to create a badge');
        $i->sendRequest($badgePayload);
        $i->seeResponseCodeIs(200);
        $badge = $i->grabFirstBadgeByTitle($name);

        /** delete the Badge
         */
        #WHEN
        $badgePayloadDelete = new Payload\Badge\Delete($badge->getId());
        $i->amGoingTo('Send request to delete a learning moment');
        $i->sendRequest($badgePayloadDelete);

        #THEN
        $i->seeResponseCodeIs(200);
    }
}

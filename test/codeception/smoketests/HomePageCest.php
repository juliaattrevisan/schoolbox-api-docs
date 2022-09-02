<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace SmokeTests;

use IntegrationTester;
use Payload;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Textbox\Component\Homepage\Controller as TextboxComponent;

/** @noinspection PhpUnused */
class HomePageCest
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

    /** @noinspection PhpUnused */
    /**
     * @param IntegrationTester $i
     */
    public function createFolder(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $homepageTitle = 'Testing Folder (Homepage) Create at ' . $timestamp;

        #WHEN
        $createFolderRequest = new Payload\Resource\Folder\Create();
        $createFolderRequest->setTemplate(Payload\Resource\Folder\Modify::TEMPLATE_BLANK);
        $createFolderRequest->setName($homepageTitle);
        $i->sendRequest($createFolderRequest);

        #THEN
        $i->seeResponseCodeIs(303);
        $i->canSeeHttpHeader('location', '/');
    }

    /** @noinspection PhpUnused */
    /**
     * @param IntegrationTester $i
     */
    public function createFolderWithCurriculumTaggingOff(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $homepageTitle = 'Testing Folder (Homepage) Create with curriculum tagging off at ' . $timestamp;

        #WHEN
        $createFolderRequest = new Payload\Resource\Folder\Create();
        $createFolderRequest->setTemplate(Payload\Resource\Folder\Modify::TEMPLATE_BLANK);
        $createFolderRequest->setName($homepageTitle);
        $createFolderRequest->unsetCurriculumCodes();
        $i->sendRequest($createFolderRequest);

        #THEN
        $i->seeResponseCodeIs(303);
        $i->canSeeHttpHeader('location', '/');
    }

    /**
     * Create a homepage and uploads the file to the homepage component directly. Drag drop path
     *
     * @param IntegrationTester $i
     */
    public function homepageUploadFileAjax(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $homepage_title = 'Testing Homepage - Homepage File Uploads using drag and drop ' . $timestamp;

        $folderPayload = new Payload\Resource\Folder\Create();
        $folderPayload->setName($homepage_title);
        $folderPayload->setTemplate(11);

        $i->amGoingTo('Send request create homepage');
        $i->sendRequest($folderPayload);
        $i->seeResponseCodeIs(303);
        $homePage = $i->grabFirstFolderByTitle($homepage_title);
        $i->seeHttpHeader('location', "/");

        $image_name = "homepageUploadTestDragDrop{$timestamp}.png";
        $image_location = '/UploadTestFiles/fish2.png';

        #WHEN
        $ajaxUploadFileHomepage = new Payload\Resource\File\AjaxUpload($homePage->getId(), 'file', $image_name, $image_location);
        $ajaxUploadFileHomepage->setHidden(0);
        $i->amGoingTo('Drag drop upload a file');
        $i->sendRequest($ajaxUploadFileHomepage);

        #THEN
        $i->seeResponseCodeIs(201);
    }

    /**
     * Create folder and uploads file to folder directly. Drag drop path.
     *
     * @param IntegrationTester $i
     */
    public function resourcesUploadFileAjax(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $folder_title = 'Testing Folder - Resource File Uploads using drag drop ' . $timestamp;

        $folderPayload = new Payload\Resource\Folder\Create();
        $folderPayload->setName($folder_title);

        $i->amGoingTo('Send request create folder');
        $i->sendRequest($folderPayload);
        $i->seeResponseCodeIs(303);
        $folder = $i->grabFirstFolderByTitle($folder_title);
        $i->seeHttpHeader('location', "/");

        $image_name = "resourcesUploadTestDragDrop{$timestamp}.png";
        $image_location = '/UploadTestFiles/fish2.png';

        #WHEN
        $ajaxUploadFile = new Payload\Resource\File\AjaxUpload($folder->getId(), 'file', $image_name, $image_location);
        $ajaxUploadFile->setVisibility(1);
        $i->amGoingTo('Drag drop upload a file');
        $i->sendRequest($ajaxUploadFile);

        #THEN
        $i->seeResponseCodeIs(201);
    }

    /**
     * Uploads a file to storage on schoolbox.
     *
     * @param IntegrationTester $i
     */
    public function storageUploadDragDrop(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $image_name = "storageUploadTestDragDrop{$timestamp}.png";
        $image_location = '/UploadTestFiles/fish2.png';

        #WHEN
        $storageUpload = new Payload\Resource\File\StorageUpload('upload', $image_name, $image_location);
        $i->sendRequest($storageUpload);

        #THEN
        $i->seeResponseCodeIs(200);
    }

    /**
     * Creates a resource folder and uploads a file via storing it first. The file upload form path.
     *
     * @param IntegrationTester $i
     */
    public function postFileUploadFileForm(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $folder_title = 'Testing Folder - Resource File Uploads using file upload form' . $timestamp;
        $image_name = "fileFormUploadTest{$timestamp}.png";
        $image_location = '/UploadTestFiles/fish2.png';
        $image_description = 'Automated uploaded file';

        $folderPayload = new Payload\Resource\Folder\Create();
        $folderPayload->setName($folder_title);

        $i->amGoingTo('Send request create folder');
        $i->sendRequest($folderPayload);
        $i->seeResponseCodeIs(303);
        $folder = $i->grabFirstFolderByTitle($folder_title);
        $i->seeHttpHeader('location', "/");

        $storageUpload = new Payload\Resource\File\StorageUpload('upload', $image_name, $image_location);
        $i->amGoingTo('Upload image to storage');
        $i->sendRequest($storageUpload);
        $i->seeResponseCodeIs(200);
        $json_reply = json_decode($i->grabResponse(), true);

        #WHEN
        $uploadFile = new Payload\Resource\File\FileUploadForm($folder->getId(), $image_name, $image_description, $json_reply['hash']);
        $i->amGoingTo('Submit the upload file form');
        $i->sendRequest($uploadFile);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', "/");
        $i->sendGET("https://vagrant.dev.schoolbox.cloud/");
        $i->seeResponseCodeIs(200);
    }

    /**
     * Creates a Group folder, adds Group members, updates their access level
     *
     * @param IntegrationTester $i
     */
    public function setUpUsers(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $student        = $i->grabUser('jreed');
        $teacher        = $i->grabUser('jcoles');
        $admin          = $i->grabUser('alaress');
        $homepage_title = 'Testing Home Page Add Members with Permissions at ' . $timestamp;

        $folderPayload = new Payload\Resource\Folder\Create();
        $folderPayload->setTemplate(Payload\Resource\Folder\Modify::TEMPLATE_BLANK);
        $folderPayload->setName($homepage_title);
        $folderPayload->setCreateGroup(true);

        $i->amGoingTo('Send request create homepage with group');
        $i->sendRequest($folderPayload);
        $i->seeResponseCodeIs(303);
        $homePage = $i->grabFirstFolderByTitle($homepage_title);
//        $i->seeHttpHeader('location', "/group/modify/{$homePage->getId()}");

        #WHEN
        $memberPayload = new Payload\Group\AddMembers($homePage->getId());
        $memberPayload->setNewMembers([$student->getId(), $admin->getId(), $teacher->getId()]);
        $i->amGoingTo('Add group members to homepage #' . $homePage->getId());
        $i->sendRequest($memberPayload);

        #THEN
        $i->seeResponseCodeIs(200);
//        $i->seeHttpHeader('location', '/group/modify/' . $homePage->getId());

        #WHEN
        $accessLevelPayload = new Payload\Group\ModifyAccessLevel($homePage->getId());
        $accessLevelPayload->setMemberAccessLevel($student->getId(), UserAccess::ACCESS_READ);
        $accessLevelPayload->setMemberAccessLevel($teacher->getId(), UserAccess::ACCESS_WRITE);
        $accessLevelPayload->setMemberAccessLevel($admin->getId(), UserAccess::ACCESS_FULL);
        $i->sendRequest($accessLevelPayload);

        #THEN
        $i->seeResponseCodeIs(200);
//        $i->seeHttpHeader('location', '/group/modify/' . $homePage->getId());
    }


    /**
     * Set up text component on homepage using curl requests.
     * Sets up a text box component, creates text in it, modifies it, deletes it.
     * @param IntegrationTester $i
     */
    public function setUpTextComponent(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $homepage_title = 'Testing Home Page Use Text Box Component at ' . $timestamp;

        $folderPayload = new Payload\Resource\Folder\Create();
        $folderPayload->setTemplate(Payload\Resource\Folder\Modify::TEMPLATE_BLANK);
        $folderPayload->setName($homepage_title);
        $i->amGoingTo('Send request create homepage');
        $i->sendRequest($folderPayload);
        $i->seeResponseCodeIs(303);
        $homePage = $i->grabFirstFolderByTitle($homepage_title);

        //adding a text box and checking status 200
        #WHEN
        $textboxComponentDefinition = $i->grabComponentDefinitionByClass(TextboxComponent::class);
        $textComponentPayload = new Payload\Homepage\Component\Config($homePage->getId());
        $textComponentPayload->setFormParam('add', $textboxComponentDefinition->getId());
        $i->amGoingTo('Add text component to homepage #' . $homePage->getId());
        $i->sendRequest($textComponentPayload);

        #THEN
        $i->seeResponseCodeIs(200);

        #WHEN
        $capture_new_component_id = '/div id=\'component(\d+)\'/';
        preg_match($capture_new_component_id, $i->grabResponse(), $new_component_id_matches);
        $created_component_id = (int)$new_component_id_matches[1];
        $textComponentPayload = new Payload\Homepage\Component\Config($homePage->getId());
        $textComponentPayload->setFormParam('type', 'components');
        $textComponentPayload->setFormParam(Payload\Homepage\Component\Config::HOMEPAGE_COMPONENT_POSITION['top'], $created_component_id);
        $i->amGoingTo('send request drop component #' . $created_component_id . ' onto homepage #' . $homePage->getId());
        $i->sendRequest($textComponentPayload);

        #THEN
        $i->seeResponseCodeIs(200);

        //adding a heading and body into the text box (post trait)
        #WHEN
        $heading = 'Text Component Test Create Text at ' . $timestamp;
        $text    = 'This text has been created';
        $textBoxPayload = new Payload\Homepage\Component\TextBoxCreate($homePage->getId(), $created_component_id);
        $textBoxPayload->setHeading($heading);
        $textBoxPayload->setBody($text);
        $i->amGoingTo('send request create text on component #' . $created_component_id . ' on homepage #' . $homePage->getId());
        $i->sendRequest($textBoxPayload);

        #THEN
        $i->seeResponseCodeIs(200);

        //getting the folder id for the text box created above to get it updated in the next test
        $json_reply = json_decode($i->grabResponse(), true);
        $createdFolderId = ($json_reply['id']);

        //modifying the textbox heading and body (put trait)
        $heading = 'Getting this title updated at ' . $timestamp;
        $text    = 'Body text updated at' . $timestamp;
        $textBoxModifyPayload = new Payload\Homepage\Component\TextBoxModify($homePage->getId(), $createdFolderId);
        $textBoxModifyPayload->setHeading($heading);
        $textBoxModifyPayload->setBody($text);
        $i->amGoingTo('Update the text on the text box heading and body #' . $homePage->getId());
        $i->sendRequest($textBoxModifyPayload);

        //adding a text box to be deleted
        #WHEN
        $textComponentPayload = new Payload\Homepage\Component\Config($homePage->getId());
        $textComponentPayload->setFormParam('add', $textboxComponentDefinition->getId());
        $i->amGoingTo('Add text component to homepage to be deleted #' . $homePage->getId());
        $i->sendRequest($textComponentPayload);

        preg_match($capture_new_component_id, $i->grabResponse(), $secondTextboxMatches);
        $deleted_component_id = (int)$secondTextboxMatches[1];
        $textComponentPayload = new Payload\Homepage\Component\Config($homePage->getId());
        $textComponentPayload->setFormParam('type', 'components');
        $textComponentPayload->setFormParam(Payload\Homepage\Component\Config::HOMEPAGE_COMPONENT_POSITION['top'], $deleted_component_id);
        $i->amGoingTo('send request drop component #' . $deleted_component_id . ' onto homepage #' . $homePage->getId());
        $i->sendRequest($textComponentPayload);

        #THEN
        $i->seeResponseCodeIs(200);

        //creating a heading and body into the text box (post trait)
        $heading = 'Text Component Test Deleted Text at ' . $timestamp;
        $text    = 'This text has been deleted';
        $textBoxPayload = new Payload\Homepage\Component\TextBoxCreate($homePage->getId(), $deleted_component_id);
        $textBoxPayload->setHeading($heading);
        $textBoxPayload->setBody($text);
        $i->amGoingTo('send request create text on component #' . $created_component_id . ' on homepage #' . $homePage->getId());
        $i->sendRequest($textBoxPayload);
        $i->seeResponseCodeIs(200);


        //deleting the text box and checking response is 200
        $textBox = $i->grabTextBoxByHeading($heading);
        $textBoxDelete = new Payload\Homepage\Component\TextBoxDelete($homePage->getId(), $textBox->getId());
        $i->amGoingTo('send request delete text box content on homepage #' . $homePage->getId());
        $i->sendRequest($textBoxDelete);
        $i->seeResponseCodeIs(200);

        $i->sendGET("https://vagrant.dev.schoolbox.cloud/homepage/{$homePage->getId()}");
        $i->seeResponseCodeIs(200);
    }
}

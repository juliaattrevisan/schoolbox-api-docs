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

use Component_Homepage_TextBoxController;
use IntegrationTester;
use Payload;
use Schoolbox\Permission\Access\UserAccess;

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
        $createFolderRequest->setTemplate(Payload\Resource\Folder\Create::TEMPLATE_BLANK);
        $createFolderRequest->setName($homepageTitle);
        $i->sendRequest($createFolderRequest);

        #THEN
        $i->seeResponseCodeIs(303);
        $i->canSeeHttpHeader('location', '/');
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
        $student        = $i->grabUser('drhodes');
        $teacher        = $i->grabUser('zsmith');
        $admin          = $i->grabUser('alaress');
        $homepage_title = 'Testing Home Page Add Members with Permissions at ' . $timestamp;

        $folderPayload = new Payload\Resource\Folder\Create();
        $folderPayload->setTemplate(Payload\Resource\Folder\Create::TEMPLATE_BLANK);
        $folderPayload->setName($homepage_title);
        $folderPayload->setCreateGroup(true);

        $i->amGoingTo('Send request create homepage with group');
        $i->sendRequest($folderPayload);
        $i->seeResponseCodeIs(303);
        $homePage = $i->grabFirstFolderByTitle($homepage_title);
        $i->seeHttpHeader('location', "/group/modify/{$homePage->getId()}");

        #WHEN
        $memberPayload = new Payload\Group\AddMembers($homePage->getId());
        $memberPayload->setNewMembers([$student->getId(), $admin->getId(), $teacher->getId()]);
        $i->amGoingTo('Add group members to homepage #' . $homePage->getId());
        $i->sendRequest($memberPayload);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', 'https://vagrant.dev.schoolbox.cloud/group/modify/' . $homePage->getId());

        #WHEN
        $accessLevelPayload = new Payload\Group\ModifyAccessLevel($homePage->getId());
        $accessLevelPayload->setMemberAccessLevel($student->getId(), UserAccess::ACCESS_READ);
        $accessLevelPayload->setMemberAccessLevel($teacher->getId(), UserAccess::ACCESS_WRITE);
        $accessLevelPayload->setMemberAccessLevel($admin->getId(), UserAccess::ACCESS_FULL);
        $i->sendRequest($accessLevelPayload);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', 'https://vagrant.dev.schoolbox.cloud/group/modify/' . $homePage->getId());
    }


    /**
     * Set up text component on homepage using curl requests. Sets up a text component, creates text in it, deletes it.
     *
     * Fires off create homepage.
     *
     * @param IntegrationTester $i
     */
    public function setUpTextComponent(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp      = time();
        $homepage_title = 'Testing Home Page Use Text Box Component at ' . $timestamp;

        $folderPayload = new Payload\Resource\Folder\Create();
        $folderPayload->setTemplate(Payload\Resource\Folder\Create::TEMPLATE_BLANK);
        $folderPayload->setName($homepage_title);
        $i->amGoingTo('Send request create homepage');
        $i->sendRequest($folderPayload);
        $i->seeResponseCodeIs(303);
        $homePage = $i->grabFirstFolderByTitle($homepage_title);

        #WHEN
        $textboxComponentDefinition = $i->grabComponentDefinitionByClass(Component_Homepage_TextBoxController::class);
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

        #WHEN
        $heading = 'Text Component Test Create Text at ' . $timestamp;
        $text    = '<p>This text has been created</p>';
        $textBoxPayload = new Payload\Homepage\Component\TextBoxCreate($homePage->getId(), $created_component_id);
        $textBoxPayload->setHeading($heading);
        $textBoxPayload->setBody($text);
        $i->amGoingTo('send request create text on component #' . $created_component_id . ' on homepage #' . $homePage->getId());
        $i->sendRequest($textBoxPayload);

        #THEN
        $i->seeResponseCodeIs(302);

        #WHEN
        $textComponentPayload = new Payload\Homepage\Component\Config($homePage->getId());
        $textComponentPayload->setFormParam('add', $textboxComponentDefinition->getId());
        $i->amGoingTo('Add text component to homepage #' . $homePage->getId());
        $i->sendRequest($textComponentPayload);

        preg_match($capture_new_component_id, $i->grabResponse(), $secondTextboxMatches);
        $deleted_component_id = (int)$secondTextboxMatches[1];
        $textComponentPayload = new Payload\Homepage\Component\Config($homePage->getId());
        $textComponentPayload->setFormParam('type', 'components');
        $textComponentPayload->setFormParam(Payload\Homepage\Component\Config::HOMEPAGE_COMPONENT_POSITION['top'], $deleted_component_id);
        $i->amGoingTo('send request drop component #' . $deleted_component_id . ' onto homepage #' . $homePage->getId());
        $i->sendRequest($textComponentPayload);

        $i->seeResponseCodeIs(200);

        $heading = 'Text Component Test Deleted Text at ' . $timestamp;
        $text    = '<p>This text has been deleted</p>';
        $textBoxPayload = new Payload\Homepage\Component\TextBoxCreate($homePage->getId(), $deleted_component_id);
        $textBoxPayload->setHeading($heading);
        $textBoxPayload->setBody($text);
        $i->amGoingTo('send request create text on component #' . $created_component_id . ' on homepage #' . $homePage->getId());
        $i->sendRequest($textBoxPayload);
        $i->seeResponseCodeIs(302);


        $textBox = $i->grabTextBoxByHeading($heading);
        $textBoxDelete = new Payload\Homepage\Component\TextboxDelete($homePage->getId(), $textBox->getId());
        $i->amGoingTo('send request delete text box content on homepage #' . $homePage->getId());
        $i->sendRequest($textBoxDelete);
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', "https://vagrant.dev.schoolbox.cloud/homepage/{$homePage->getId()}");

        $i->sendGET("https://vagrant.dev.schoolbox.cloud/homepage/{$homePage->getId()}");
        $i->seeResponseCodeIs(200);
    }
}

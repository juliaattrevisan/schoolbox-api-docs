<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace SmokeTests;

use IntegrationTester;
use Payload;
use Payload\Base;
use Payload\GroupMember as GroupMemberPayload;
use SchoolboxHelper\SchoolboxActionsConstants;

class HomePageCest {

    /**
     * Push a login payload to Schoolbox each test to ensure session is active and logged in.
     *
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i)
    {
        $i->loginAdmin();
    }

    /** Create homepage. creates a homepage through a curl payload
     *
     * @param IntegrationTester $i
     * @param Payload\Folder    $folderPayload
     */
    public function createHomepage(IntegrationTester $i, Payload\Folder  $folderPayload){
        #GIVEN
        $timestamp = time();
        $homepage_title='Testing Home Page Create Homepage at '.$timestamp;

        #WHEN
        $folderPayload->setTemplate(Payload\Folder ::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($homepage_title);
        $response= $i->sendCurlRequest($folderPayload, true, 'Send request create homepage');
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/'=>'303'],
            [IntegrationTester::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Set up users tests setting up users on group page via curl requests.
     *
     *  Fires off create homepage.
     *
     * @param IntegrationTester  $i
     * @param Payload\Folder     $folderPayload
     * @param GroupMemberPayload $memberPayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function setUpUsers(IntegrationTester $i, Payload\Folder $folderPayload, GroupMemberPayload $memberPayload){

        #GIVEN
        $timestamp = time();
        $student = $i->getUser('drhodes');
        $teacher = $i->getUser('zsmith');
        $admin = $i->getUser('alaress');
        $homepage_title='Testing Home Page Add Members with Permissions at '.$timestamp;

        $folderPayload->setTemplate(Payload\Folder::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($homepage_title);
        $i->sendCurlRequest($folderPayload, false, 'Send request create homepage');
        $homePage=$i->getFirstFolderByTitle($homepage_title);

        #WHEN
        $memberPayload->setFolderGroup($homePage->getId());
        $memberPayload->setNewMember($student->getId());
        $memberPayload->setNewMember($admin->getId());
        $memberPayload->setNewMember($teacher->getId());
        $response = $i->sendCurlRequest($memberPayload, true, 'Add group members to homepage #'.$homePage->getId());

        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/group/modify/'.$homePage->getId()=>'302'],
            [IntegrationTester::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $memberPayload->resetMemberAdjustmentList();
        $memberPayload->setNewMemberPermission($student->getId(), 'read');
        $memberPayload->setNewMemberPermission($teacher->getId(), 'write');
        $memberPayload->setNewMemberPermission($admin->getId(), 'admin');
        $response = $i->sendCurlRequest($memberPayload, true, 'Add group members permissions to homepage #'.$homePage->getId());

        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/group/modify/'.$homePage->getId()=>'302'],
            [IntegrationTester::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

    }

    /** Set up text component on homepage using curl requests. Sets up a text component, creates text in it, deletes it.
     *
     * Fires off create homepage.
     *
     * @param IntegrationTester $i
     * @param Payload\Folder    $folderPayload
     * @param Payload\TextBoxComponent $textBoxPayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function setUpTextComponent(IntegrationTester $i, Payload\Folder $folderPayload, Payload\TextBoxComponent $textBoxPayload){

        #GIVEN
        $timestamp = time();
        $homepage_title='Testing Home Page Use Text Box Component at '.$timestamp;

        $folderPayload->setTemplate(Payload\Folder::TEMPLATE_HOMEPAGE_ID);
        $folderPayload->setName($homepage_title);
        $i->sendCurlRequest($folderPayload, false, 'Send request create homepage');
        $homePage=$i->getFirstFolderByTitle($homepage_title);

        #WHEN
        $text_component_class=$i->getComponentByClass('Component_Homepage_TextBoxController');

        $textComponentPayload=new Base();
        $textComponentPayload->setPage('/homepage/configForm.php');
        $textComponentPayload->setReferer('/homepage/'.$homePage->getId());
        $textComponentPayload->setFormParam('add', $text_component_class->getId());
        $textComponentPayload->setFormParam('cid', $homePage->getId());

        $response = $i->sendCurlRequest($textComponentPayload, true, 'Add text component to homepage #'.$homePage->getId());
        $expectedUrlsReturned= [[IntegrationTester::NO_REDIRECT=>'200']];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $capture_new_component_id='/div id=\'component(\d+)\'/';
        preg_match($capture_new_component_id, $response->getBody(), $new_component_id_matches);
        $created_component_id = $new_component_id_matches[1];
        $textComponentPayload->setFormParam('type', 'components');
        $textComponentPayload->setFormParam('column'.SchoolboxActionsConstants::HOMEPAGE_COMPONENT_POSITION['top'], $created_component_id);
        $textComponentPayload->setFormParam('cid', $homePage->getId());

        $response = $i->sendCurlRequest($textComponentPayload, true, 'send request drop component #'.$created_component_id.' onto homepage #'.$homePage->getId());
        $expectedUrlsReturned= [[IntegrationTester::NO_REDIRECT=>'200']];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $heading='Text Component Test Create Text at '.$timestamp;
        $text='<p>This text has been created</p>';
        $textBoxPayload->setHomepageId($homePage->getId());
        $textBoxPayload->setComponentId(intval($created_component_id));
        $textBoxPayload->setHeading($heading);
        $textBoxPayload->setBody($text);
        $response = $i->sendCurlRequest($textBoxPayload, true, 'send request create text on component #'.$created_component_id.' on homepage #'.$homePage->getId());

        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/homepage/'.$homePage->getId()=>'302'],
            [IntegrationTester::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $textComponentPayload=new Base();
        $textComponentPayload->setPage('/homepage/configForm.php');
        $textComponentPayload->setReferer('/homepage/'.$homePage->getId());
        $textComponentPayload->setFormParam('add', $text_component_class->getId());
        $textComponentPayload->setFormParam('cid', $homePage->getId());

        $response = $i->sendCurlRequest($textComponentPayload, true, 'Add text component to homepage #'.$homePage->getId());

        preg_match($capture_new_component_id, $response->getBody(), $new_component_id_matches);
        $deleted_component_id = $new_component_id_matches[1];
        $textComponentPayload->setFormParam('type', 'components');
        $textComponentPayload->setFormParam('column'.SchoolboxActionsConstants::HOMEPAGE_COMPONENT_POSITION['top'], $deleted_component_id);
        $textComponentPayload->setFormParam('cid', $homePage->getId());
        $i->sendCurlRequest($textComponentPayload, false, 'send request drop component #'.$deleted_component_id.' onto homepage #'.$homePage->getId());

        $heading='Text Component Test Deleted Text at '.$timestamp;
        $text='<p>This text has been deleted</p>';
        $textBoxPayload->setHomepageId($homePage->getId());
        $textBoxPayload->setComponentId($deleted_component_id);
        $textBoxPayload->setHeading($heading);
        $textBoxPayload->setBody($text);
        $i->sendCurlRequest($textBoxPayload, false, 'send request create text on component #'.$created_component_id.' on homepage #'.$homePage->getId());


        $textBox = $i->getTextBoxByHeading($heading);
        $textBoxPayload->setReferer('/homepage/'.$homePage->getId());
        $textBoxPayload->deleteFormParams();
        $textBoxPayload->setMethod('GET');
        $textBoxPayload->setQueryString('delete', $textBox->getId());
        $textBoxPayload->setQueryString('originID', $homePage->getId());
        $response = $i->sendCurlRequest($textBoxPayload, true, 'send request delete text box content on homepage #'.$homePage->getId());

        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/homepage/'.$homePage->getId()=>'302'],
            [IntegrationTester::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

    }
}

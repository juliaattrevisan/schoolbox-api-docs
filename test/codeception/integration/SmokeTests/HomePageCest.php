<?php

namespace SmokeTests;

use IntegrationTester;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\GuzzleException;
use SchoolboxHelper\SchoolboxActionsConstants;

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class HomePageCest {
    /**
     * Smoke test hompeage post forms include following actions:
     * Create homepage
     * Add users to homepage
     * Set permissions for users in group permissions for homepage
     * Add text component
     * delete fresh text input in the text component that we created.
     * create text input in the text component
     *
     * Each of these in the future itteration should be split into sepperate test functions.
     * Also in future itterations we need to cover each component.
     *
     * @param IntegrationTester $i
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\External\Exception
     */
    public function SmokeHomepagePOST(IntegrationTester $i){
        #GIVEN
        $i->loginAdmin(); #we logged in as alaress
        $homepage_title='Testing Home Page for Homepage Smoke';
        $student = $i->getUser('drhodes');
        $teacher = $i->getUser('zsmith');
        $admin = $i->getUser('alaress');

        #WHEN
        $response = $i->createHomepage($homepage_title);
        $homePageId=$i->getFirstHomepageIdByTitle($homepage_title);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('Create homepage :'.$homePageId);

        #THEN
        $i->assertEquals(303, $redirect_history_status[0], 'I expect that we get redirected after I create a homepage.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/', $redirect_history[0], 'I expect that we get redirected to index after homepage is created.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $response = $i->addUsersToHomepageGroup($homePageId, [$student->getId(), $admin->getId(), $teacher->getId()]);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('Add users to homepage ID:'.$homePageId);

        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after we add users.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/group/modify/'.$homePageId, $redirect_history[0], 'I expect that we get redirected to the homepage modify group section after we add users.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $response = $i->setHomepageGroupUserPermission($homePageId, [$student->getId()=>1, $teacher->getId()=>2, $admin->getId()=>3]);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('Change permission for group users on homepage ID:'.$homePageId);

        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after edit permissions of users.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/group/modify/'.$homePageId, $redirect_history[0], 'I expect that we get redirected to the homepage modify group section after edit permissions of users.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

        #WHEN
        $text_component_class=$i->getComponentByClass('Component_Homepage_TextBoxController');
        $text='<p>This is test Text</p>';
        $response = $i->addComponentToHomepage($text_component_class->getId(), $homePageId, SchoolboxActionsConstants::HOMEPAGE_COMPONENT_POSITION['top']);
        $i->comment('Added the Text component to Homepage.');

        #THEN
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after text component creates.');

        #WHEN
        $homepage_text_component = $i->getComponentOnHomepage($homePageId, $text_component_class, 'top');
        $heading='Heading Component Test - Delete me';
        $i->addTextBoxToTextComponent($homePageId, $homepage_text_component, $text, $heading);
        $response = $i->deleteTextBoxToTextComponent($homePageId, $heading);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('Delete a text input we freshly created.');

        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I delete text input.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/homepage/'.$homePageId, $redirect_history[0], 'I expect that we get redirected to the homepage after deleting text input in text component.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');


        #WHEN
        $heading='Heading Component Test';
        $response = $i->addTextBoxToTextComponent($homePageId, $homepage_text_component, $text, $heading);
        $redirect_history = $response->getHeader('X-Guzzle-Redirect-History');
        $redirect_history_status = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $i->comment('create a fresh text input into the text component');

        #THEN
        $i->assertEquals(302, $redirect_history_status[0], 'I expect that we get redirected after I create text input.');
        $i->assertEquals('https://vagrant.dev.schoolbox.cloud/homepage/'.$homePageId, $redirect_history[0], 'I expect that we get redirected to the homepage after creating text input in component.');
        $i->assertEquals(200, $response->getStatusCode(), 'I expect that we get a 200 after redirection.');

    }
}
<?php

namespace SmokeTests;

use GuzzleHelper\GuzzleConstants;
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
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/'=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];
        $i->comment('Create homepage :'.$homePageId);

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $response = $i->addUsersToHomepageGroup($homePageId, [$student->getId(), $admin->getId(), $teacher->getId()]);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/group/modify/'.$homePageId=>'302'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];
        $i->comment('Add users to homepage ID:'.$homePageId);

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $response = $i->setHomepageGroupUserPermission($homePageId, [$student->getId()=>1, $teacher->getId()=>2, $admin->getId()=>3]);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/group/modify/'.$homePageId=>'302'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];
        $i->comment('Change permission for group users on homepage ID:'.$homePageId);

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $text_component_class=$i->getComponentByClass('Component_Homepage_TextBoxController');
        $text='<p>This is test Text</p>';
        $response = $i->addComponentToHomepage($text_component_class->getId(), $homePageId, SchoolboxActionsConstants::HOMEPAGE_COMPONENT_POSITION['top']);
        $expectedUrlsReturned= [[GuzzleConstants::NO_REDIRECT=>'200']];
        $i->comment('Added the Text component to Homepage.');

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

        #WHEN
        $homepage_text_component = $i->getComponentOnHomepage($homePageId, $text_component_class, 'top');
        $heading='Heading Component Test - Delete me';
        $i->addTextBoxToTextComponent($homePageId, $homepage_text_component, $text, $heading);
        $response = $i->deleteTextBoxToTextComponent($homePageId, $heading);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/homepage/'.$homePageId=>'302'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];
        $i->comment('Delete a text input we freshly created.');

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);


        #WHEN
        $heading='Heading Component Test';
        $response = $i->addTextBoxToTextComponent($homePageId, $homepage_text_component, $text, $heading);
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/homepage/'.$homePageId=>'302'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];
        $i->comment('create a fresh text input into the text component');

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);

    }
}
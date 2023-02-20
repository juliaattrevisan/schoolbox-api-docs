<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Homepage;

use AcceptanceTester;
use Codeception\Util\Locator;
use Page\Folder\CreateFolder;
use Page\Homepage;
use Exception;
use Page\Resources\Resources;

/**
 * Test for attaching a textbox to a page.
 */
class TextboxCest
{
    private const CREATE_FOLDER_URL = 'https://vagrant.dev.schoolbox.cloud/resource/folder/0/create';


    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * @return void
     */
    public function _before(AcceptanceTester $i)
    {
        $i->logout();
    }

    /**
     * @param AcceptanceTester $i
     * @beforeSuite
     */
    public function createHomepage(AcceptanceTester $i): void
    {
        $i->loginAsAdmin();
        $createFolder = new createFolder($i);
        $i->amGoingTo("create a homepage folder");
        $i->amOnPage($createFolder::URL);
        $createFolder->waitCreateFolderTitleToBePresented();
        $folderName = "Test TextBox Homepage";
        $homepageTemplateOption = "Blank";
        $i->amGoingTo("type a folder name, select a template option and set access permissions");
        $createFolder->typeName($folderName);
        $createFolder->setHomepageTemplate($homepageTemplateOption);
        $createFolder->setAccessPermission('groupWrite1');
        $createFolder->setAccessPermission('groupWrite2');
        $createFolder->setAccessPermission('groupRead3');
        $createFolder->setAccessPermission('groupRead4');
        $createFolder->setAccessPermission('groupRead5');
        $createFolder->setAccessPermission('groupRead6');
        $createFolder->clickCreateFolderButton();
        $i->amGoingTo("navigate to the resources page and check the new homepage folder is presented");
        $i->amOnPage($createFolder::URL_RESOURCES);
        $i->canSee("Test TextBox Homepage");
    }

    /**
     * Test that a news item I create retains the settings I chose
     *
     * as jcoles I will navigate to a homepage,
     * add a textbox component with heading and content
     * and verify it is properly presented
     *
     * @param AcceptanceTester     $i
     * @param Homepage             $homepage
     * @param Homepage\TextboxForm $textboxForm
     * @beforeSuite
     * @depends createHomepage
     * @throws Exception
     */
    public function createTextbox(AcceptanceTester $i, Homepage $homepage, Homepage\TextboxForm $textboxForm): void
    {
        $i->login("jcoles", "password");
        $createFolder = new createFolder($i);
        $resources = new Resources($i);
        $folderName = "Test TextBox Homepage";
        $i->amOnPage($createFolder::URL_RESOURCES);
        $i->waitForText($folderName);
        $resources->clickFolderName($folderName);

        $i->waitForText("Homepage", 5);
        sleep(1);
        $i->click("a.icon-home");

        $homepage->addComponent(Homepage::ELEMENT_ADD_COMPONENT_SECTION_INFORMATION, 'Text Box');

        // Enter content into the new component
        $layout   = Homepage::ELEMENT_COMPONENT_LAYOUT_TOP;
        $position = 1;

        $homepage->clickComponentEmptyStateLink($layout, $position);
        sleep(2);
        $heading = 'Test Text Box Title';
        $content = 'I am textbox content';
        $textboxForm
            ->fillHeading($heading)
            ->submit()
            ->fillContent($content)
            ->save();

        // Verify what I entered is the heading and body
        $i->canSee("Test Text Box Title");
        $i->canSee("I am textbox content");
    }
}

<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Acceptance\Homepage;

use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Common;
use Tests\Support\Page\Folder\CreateFolder;
use Tests\Support\Page\Homepage;
use Exception;
use Tests\Support\Page\Homepage\ManageAllTextbox;
use Tests\Support\Page\Resources\Resources;

/**
 * Test for attaching a textbox to a page.
 */
class TextboxCest
{
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
     */

    /**
     * Soft deleting the homepage with certain name to not impact the test
     *
     * @param AcceptanceTester $i
     */
    public function beforeSuite(AcceptanceTester $i): void
    {
        $i->amGoingTo("update delete_at for the textbox (folder table) so it does not impact the news tests");
        $i->updateInDatabase('folder', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => "Test TextBox Homepage%"]);
    }

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
     * Test that a textbox I create retains the settings I chose
     *
     * as alaress I will navigate to a homepage,
     * add a textbox component with heading and content
     * and verify it is properly presented
     *
     * @param AcceptanceTester     $i
     * @param Homepage             $homepage
     * @param Homepage\TextboxForm $textboxForm
     * @depends createHomepage
     *
     * @depends createHomepage
     * @throws Exception
     */
    public function createTextbox(AcceptanceTester $i, Homepage $homepage, Homepage\TextboxForm $textboxForm): void
    {
        $i->loginAsAdmin();
        $createFolder = new createFolder($i);
        $resources = new Resources($i);
        $manageAll = new ManageAllTextbox($i);
        $folderName = "Test TextBox Homepage";

        $i->amGoingTo("Navigate to the Test TextBox Homepage via resources browser");
        $i->amOnPage($createFolder::URL_RESOURCES);
        $i->waitForText($folderName);
        $resources->clickFolderName($folderName);
        $i->waitForText("Homepage", 5);
        $resources->clickMenuItem('home');

        $i->amGoingTo("Add a new component in the homepage");
        $homepage->addComponent(Homepage::ELEMENT_ADD_COMPONENT_SECTION_INFORMATION, 'Text Box');
        $i->amGoingTo("Enter content into the new component");
        $i->waitForElement('i.icon-textbox');
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

        $i->amGoingTo("Verify what I entered is the heading and body");
        $i->canSee($heading);
        $i->canSee($content);

        $i->amGoingTo("Create another 2 text boxes");
        $manageAll->clickManageAll();
        $manageAll->clickCreateTextBox();
        $homepageId = $i->grabFromDatabase('folder', 'id', ['deleted_at' => null,'name like' => $folderName]);
        $textBoxCount = $i->grabNumRecords('textBox', ['categoryID' => $homepageId]);
        $manageAll->typeTextBoxHeading($textBoxCount, 'Second Text Box');
        $manageAll->typeTextBoxContent($textBoxCount, 'Test');
        $manageAll->clickCreateTextBox();
        $textBoxItem = $textBoxCount + 1;
        $manageAll->typeTextBoxHeading($textBoxItem, 'Third Text Box');
        $manageAll->typeTextBoxContent($textBoxItem, 'Jana Reed');
        $manageAll->clickSaveAll();
    }

    /**
     * Verifies which textbox different users can or cannot view
     *
     * as teacher and student I will navigate to a homepage,
     * check the textbox I can view
     * and also which textboxes are presented for me when I search via search bar
     *
     * @param AcceptanceTester     $i
     * @param Homepage             $homepage
     * @param Homepage\TextboxForm $textboxForm

     * @throws Exception
     */
    public function textboxVisibility(AcceptanceTester $i, Homepage $homepage, Homepage\TextboxForm $textboxForm): void
    {
        $i->loginAsAdmin();
        $folderName = "Test TextBox Homepage";
        $common = new Common($i);
        $manageAll = new ManageAllTextbox($i);

        $i->amGoingTo("As Jack Coles search for a  textbox and ensure it is presented in the search results grid");
        $i->impersonate('Jack Coles');
        $common->search('Jana Reed');
        $i->click('Clear all filters');
        $common->clickSearchTypeCheckbox('textbox');
        $i->click('Apply filters and search');
        $i->waitForElementVisible('div.alert-box');
        $i->canSee('Jana Reed', 'mark');
        $i->canSee('Third Text Box');
        $common->search('Test');
        $i->click('Clear all filters');
        $common->clickSearchTypeCheckbox('textbox');
        $i->click('Apply filters and search');
        $i->waitForElementVisible('div.alert-box');
        $i->canSee('Second Text Box', '//h6/a');
        $i->canSee('Test', 'mark');
        $i->canSee('Test Text Box Title', '//h6/a');
        $i->canSee('I am textbox content', '//article/p');

        $i->amGoingTo("As Jana Reed search for a  textbox and check whether it is presented in the search results grid or not");
        $i->impersonate('Jana Reed');
        $common->search('Jana Reed');
        $i->click('Clear all filters');
        $common->clickSearchTypeCheckbox('textbox');
        $i->click('Apply filters and search');
        $i->waitForElementVisible('div.alert-box');
        $i->cantSee('Jana Reed', 'mark');
        $i->cantSee('Third Text Box', '//h6/a');
        $common->search('Test');
        $i->click('Clear all filters');
        $common->clickSearchTypeCheckbox('textbox');
        $i->click('Apply filters and search');
        $i->waitForElementVisible('div.alert-box');
        $i->cantSee('Second Text Box', '//h6/a');
        $i->cantSee('Test', 'mark');
        $i->canSee('Test Text Box Title', '//h6/a');
        $i->canSee('I am textbox content', '//article/p');

        $i->amGoingTo("As Jack Coles navigate to the Test TextBox Homepage");
        $i->impersonate('Jack Coles');
        $homepageId = $i->grabFromDatabase('folder', 'id', ['deleted_at' => null,'name like' => $folderName]);
        $i->amOnPage('/homepage/' . $homepageId);
        $i->amGoingTo("Unlock the components and edit the textbox settings");
        $homepage->clickLockToggle();
        $homepage->clickComponentSettings();
        $homepage->typeTextBoxHeading('Jana to be hidden');
        $i->amGoingTo("Hide the textbox title from read only users");
        $componentId = $i->grabFromDatabase('component_instance', 'id', ['identifier' => $homepageId]);
        $homepage->uncheckComponentCheckbox($componentId, 'showComponentTitle');
        $manageAll->clickSaveAll();
        $i->amGoingTo("Update the textbox content");
        $i->click('Modify');
        $textboxForm->fillContent('Jamal to be presented');
        $textboxForm->save();

        $i->amGoingTo("As Jana Reed search for textboxes and check whether its is presented or not");
        $i->impersonate('Jana Reed');
        $common->search('Jamal');
        $i->click('Clear all filters');
        $common->clickSearchTypeCheckbox('textbox');
        $i->click('Apply filters and search');
        $i->canSee('Jamal to be presented', '//article/p');
        $common->search('Jana');
        $i->click('Apply filters and search');
        $i->canSee('No results for');
        $i->amGoingTo("Navigate to the Test TextBox Homepage to check whether the textbox is presented or not");
        $i->amOnPage('/homepage/' . $homepageId);
        $i->canSee('Jamal to be presented');
        $i->cantSee('Jana to be hidden');
    }
}

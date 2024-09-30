/**
 * test if the homepage is created only when user clicks the save button, add a few components(textbox, news and blog) into the homepage,
 * add content to the components and check its contents as different users, delete the component,
 * delete the homepage(by clicking cancel in the confirmation dialog it is not deleted, by confirming it is deleted)
 * 1. Provision sales.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance Homepage/ComponentsCest
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import TextboxForm from "@schoolboxTest/playwright/support/page/homepage/TextboxForm";
import Resources from "@schoolboxTest/playwright/support/page/resources/Resources";
import { timeout } from "@schoolboxVue/components/toast/sbxToastEvent";

test.describe("Homepage Initial Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    /**
     * when creating a homepage by filling all the mandatory fields the user forgets to click the save button
     * ensure the homepage was not created
     */

    test("create a homepage fail", async ({ tester, page }) => {
        const createFolder = new CreateFolder(page);
        const folderName = "Test Homepage";
        const homepageTemplateOption = "Blank";
        tester.isGoingTo("create a homepage folder");
        await tester.loginAsAdmin();
        await createFolder.goto();
        tester.isGoingTo("type a folder name, select a template option and set access permissions");
        await createFolder.typeName(folderName);
        await createFolder.setHomepageTemplate(homepageTemplateOption);
        await createFolder.setAccessPermission(1, "write");
        await createFolder.setAccessPermission(2, "write");
        await createFolder.setAccessPermission(3, "read");
        await createFolder.setAccessPermission(4, "read");
        await createFolder.setAccessPermission(5, "read");
        await createFolder.setAccessPermission(6, "read");
        tester.isGoingTo("Forget to submit");
        tester.isGoingTo("navigate to the resources page and check the new homepage folder is not presented");
        await createFolder.gotoResources();
        await expect(page.locator(`a.jstree-anchor:has-text("${folderName}")`)).not.toBeVisible();
    });

    /**
     * creates a homepage successfully
     */

    test("create a homepage", async ({ tester, page }) => {
        const createFolder = new CreateFolder(page);
        const folderName = "Test Homepage";
        const homepageTemplateOption = "Blank";
        tester.isGoingTo("create a homepage folder");
        await tester.loginAsAdmin();
        await createFolder.goto();
        tester.isGoingTo("type a folder name, select a template option and set access permissions");
        await createFolder.typeName(folderName);
        await createFolder.setHomepageTemplate(homepageTemplateOption);
        await createFolder.setAccessPermission(1, "write");
        await createFolder.setAccessPermission(2, "write");
        await createFolder.setAccessPermission(3, "read");
        await createFolder.setAccessPermission(4, "read");
        await createFolder.setAccessPermission(5, "read");
        await createFolder.setAccessPermission(6, "read");
        await createFolder.submit();
        tester.isGoingTo("navigate to the resources page and check the new homepage folder is presented");
        await createFolder.gotoResources();
        await expect(page.locator(`a.jstree-anchor:has-text("${folderName}")`)).toBeVisible();
    });

    /**
     * add a few components(textbox, news and blog) into the homepage and check it is added successfully
     */

    test("create elements", async ({ tester, page }) => {
        const createFolder = new CreateFolder(page);
        const folderName = "Test Homepage";
        const resources = new Resources(page);
        const homepage = new Homepage(page);
        tester.isGoingTo("navigate to the Test Homepage via resources browser");
        await createFolder.gotoResources();
        await expect(page.locator(`a.jstree-anchor:has-text("${folderName}")`)).toBeVisible();
        await resources.clickFolderName(folderName);
        await resources.clickMenuItem("home");
        await page.waitForTimeout(2000);
        tester.isGoingTo("Add a few components in the homepage");
        await homepage.addComponent("#tabs-sub-1", "Blog");
        await page.waitForSelector("i.icon-blog");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Blog_Homepage_Component_Controller"]`),
        ).toBeVisible();
        await page.reload();
        await homepage.addComponent("#tabs-sub-1", "News");
        await page.waitForSelector("i.icon-news");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Comms_News_Component_Homepage_Controller"]`),
        ).toBeVisible();
        await page.reload();
        await homepage.addComponent("#tabs-sub-1", "Text Box");
        await page.waitForSelector("i.icon-textbox");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Resource_Textbox_Component_Homepage_Controller"]`),
        ).toBeVisible();
    });

    /**
     * adds content into the components: blog, textbox and news
     * check the content is successfully added
     */

    test("use elements", async ({ tester, page, db }) => {
        const createFolder = new CreateFolder(page);
        const folderName = "Test Homepage";
        const resources = new Resources(page);
        const homepage = new Homepage(page);
        const textbox = new TextboxForm(tester, page);
        tester.isGoingTo("Navigate to the Test Homepage via resources browser");
        await createFolder.gotoResources();
        await expect(page.locator(`a.jstree-anchor:has-text("${folderName}")`)).toBeVisible();
        await resources.clickFolderName(folderName);
        await resources.clickMenuItem("home");
        await page.waitForTimeout(2000);
        const homepageId = (await db.grabFromDatabase("folder", "id", {
            deleted_at: null,
            "name like": folderName,
        })) as number;
        tester.isGoingTo("Add a blog post");
        const layout = Homepage.ELEMENT_COMPONENT_LAYOUT_TOP;
        await homepage.clickComponentEmptyStateLink(layout, 3);
        await page.fill("input[name=heading]", "Test Blog Title");
        await page.click("input[type=submit]");
        await page.waitForTimeout(2000);
        await page.goto(`/homepage/${homepageId}`);
        tester.isGoingTo("Add a news article");
        await homepage.clickComponentEmptyStateLink(layout, 2);
        await page.fill("input[name=title]", "Test News Title");
        const body = page.locator(`#body`);
        await tester.fillCKEditorByLocator(body, "wow what interesting news");
        await page.click("button[id=news-submit]");
        await page.waitForTimeout(2000);
        await page.goto(`/homepage/${homepageId}`);
        tester.isGoingTo("Add a text box");
        await homepage.clickComponentEmptyStateLink(layout, 1);
        await textbox.fillHeading("Test Text Box Title");
        await textbox.submit();
        await page.waitForTimeout(2000);
        const textboxId = (await db.grabFromDatabase("component_instance", "id", {
            sequence: 1,
            identifier: homepageId,
        })) as number;
        await textbox.fillContent("wow what interesting Text box text", textboxId);
        await textbox.save();
        await page.waitForTimeout(2000);
        tester.isGoingTo("Verify the content is added");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Blog_Homepage_Component_Controller"]`),
        ).toBeVisible();
        await expect(
            page.locator(`div[class="component-container Schoolbox_Comms_News_Component_Homepage_Controller"]`),
        ).toBeVisible();
        await expect(
            page.locator(`div[class="component-container Schoolbox_Resource_Textbox_Component_Homepage_Controller"]`),
        ).toBeVisible();
        await expect(
            page.locator(`div[class="component-container Schoolbox_Blog_Homepage_Component_Controller"]`),
        ).toContainText("Test Blog Title");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Comms_News_Component_Homepage_Controller"]`),
        ).toContainText("Test News Title");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Resource_Textbox_Component_Homepage_Controller"]`),
        ).toContainText("Test Text Box Title");
    });
});
// <?php
// /**
//  * @copyright Copyright 2018, Alaress Pty Ltd
//  * @link      http://www.alaress.com.au/
//  */

// namespace Tests\Acceptance\Homepage;

// use Codeception\Exception\ModuleException;
// use Exception;
// use Tests\Support\AcceptanceTester;
// use Codeception\Util\Locator;

// /**
//  * test if the homepage is created only when user clicks the save button, add a few components(textbox, news and blog) into the homepage,
//  * add content to the components and check its contents as different users, delete the component,
//  * delete the homepage(by clicking cancel in the confirmation dialog it is not deleted, by confirming it is deleted)
//  * 1. Provision sales.tar.gz data
//  * 2. vagrant ssh
//  * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance Homepage/ComponentsCest
//  */
// class HomepageInitialCest
// {
//     /**
//      * @var mixed
//      */
//     public $createFolderUrl;
//     public $resourcesPageUrl;

//     /**
//      * @param AcceptanceTester $i
//      * when creating a homepage by filling all the mandatory fields the user forgets to click the save button
//      * ensure the homepage was not created
//      */
//     public function createHomepageFail(AcceptanceTester $i): void
//     {
//         $i->login("alaress", "alaress");
//         $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/resource/folder/0/create');
//         $this->createFolderUrl = $i->getCurrentUrl();
//         $i->selectOption("template", "Blank");
//         $i->fillField(['name' => 'name'], 'Test Homepage');
//         $i->click(['css' => 'label[class=radio-label][for=groupWrite1]']);
//         $i->click(['css' => 'label[class=radio-label][for=groupWrite2]']);
//         $i->click(['css' => 'label[class=radio-label][for=groupRead3]']);
//         $i->click(['css' => 'label[class=radio-label][for=groupRead4]']);
//         $i->click(['css' => 'label[class=radio-label][for=groupRead5]']);
//         $i->click(['css' => 'label[class=radio-label][for=groupRead6]']);
//         echo "Forget to submit \n";
//         $i->amOnUrl("https://vagrant.dev.schoolbox.cloud/resources/");
//         $this->resourcesPageUrl = $i->getCurrentUrl();
//         $i->cantSee("Test Homepage");
//     }

//     /**
//      * @param AcceptanceTester $i
//      * creates a homepage successfully
//      */
//     public function createHomepage(AcceptanceTester $i): void
//     {
//         $i->login("alaress", "alaress");
//         var_dump($this->createFolderUrl);
//         $i->amOnUrl($this->createFolderUrl);
//         $i->selectOption("template", "Blank");
//         $i->fillField(['name' => 'name'], 'Test Homepage');
//         $i->click(['css' => 'label[class=radio-label][for=groupWrite1]']);
//         $i->click(['css' => 'label[class=radio-label][for=groupWrite2]']);
//         $i->click(['css' => 'label[class=radio-label][for=groupRead3]']);
//         $i->click(['css' => 'label[class=radio-label][for=groupRead4]']);
//         $i->click(['css' => 'label[class=radio-label][for=groupRead5]']);
//         $i->click(['css' => 'label[class=radio-label][for=groupRead6]']);
//         $i->click(['css' => 'input[type=submit]']);
//         $i->amOnUrl($this->resourcesPageUrl);
//         $i->canSee("Test Homepage");
//     }

//     /**
//      * @depends createHomepage
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * add a few components(textbox, news and blog) into the homepage and check it is added successfully
//      *
//      */
//     public function createElement(AcceptanceTester $i): void
//     {
//         $i->login("alaress", "alaress");
//         $i->amOnUrl($this->resourcesPageUrl);
//         $i->canSee("Test Homepage");
//         $testHomepage = Locator::contains('a.jstree-anchor', 'Test Homepage');
//         $i->click($testHomepage);
//         $i->waitForText("Homepage", 5);
//         sleep(1);
//         $i->click("a.icon-home");
//         $i->click(['css' => 'label[for=component-lock-toggle]']);
//         $i->canSeeElement(['css' => 'span[class=icon-unlocked]']);
//         $i->click(['css' => 'a#add-component']);
//         sleep(1);
//         $i->canSeeElement(['css' => 'div#tabs-1']);
//         #Blog Test
//         $i->click(['css' => 'a[href=\#tabs-sub-1]']);
//         $i->canSeeElement(['css' => 'ul#tabs-sub-1']);
//         $i->click("//*[@id='tabs-sub-1']/li[1]");
//         sleep(1);
//         $i->canSeeElement(['css' => 'div[class=component-container\ Schoolbox_Blog_Homepage_Component_Controller]']);
//         #News Test
//         $i->click(['css' => 'a[href=\#tabs-sub-1]']);
//         $i->canSeeElement(['css' => 'ul#tabs-sub-1']);
//         $i->click("//*[@id='tabs-sub-1']/li[4]");
//         sleep(1);
//         $i->canSeeElement(['css' => 'div[class=component-container\ Schoolbox_Comms_News_Component_Homepage_Controller]']);
//         #Text box Test
//         $i->click(['css' => 'a[href=\#tabs-sub-1]']);
//         $i->canSeeElement(['css' => 'ul#tabs-sub-1']);
//         $i->click("//*[@id='tabs-sub-1']/li[6]");
//         sleep(3);
//         $i->canSeeElement(['css' => 'div[class=component-container\ Schoolbox_Resource_Textbox_Component_Homepage_Controller]']);
//     }

//     /**
//      * @depends createHomepage
//      * @depends createElement
//      * @param AcceptanceTester $i
//      * @throws ModuleException
//      * @throws Exception
//      *
//      * adds content into the components: blog, textbox and news
//      * check the content is successfully added
//      *
//      */
//     public function useElement(AcceptanceTester $i): void
//     {
//         $i->login("alaress", "alaress");
//         $i->amOnUrl($this->resourcesPageUrl);
//         $i->canSee("Test Homepage");
//         $testHomepage = Locator::contains('a.jstree-anchor', 'Test Homepage');
//         $i->click($testHomepage);
//         $i->waitForText("Homepage", 5);
//         sleep(1);
//         $i->click("a.icon-home");
//         $home_page_url = $i->getCurrentUrl();
//         $i->canSeeElement(['css' => 'div[class=component-container\ Schoolbox_Blog_Homepage_Component_Controller]']);
//         sleep(2);
//         $i->click("Add a blog post");
//         $i->canSee("Post Blog");
//         $i->fillField("heading", "Test Blog Title");
//         $i->fillCkEditorById('body', 'Interesting blog text');
//         $i->click(['css' => 'input[type=submit]']);
//         $i->amOnUrl($home_page_url);
//         $i->canSeeElement(['css' => 'div[class=component-container\ Schoolbox_Comms_News_Component_Homepage_Controller]']);
//         sleep(5);
//         $i->click("Post a news article");
//         $i->fillField("title", "Test News Title");
//         $i->fillCkEditorById('body', 'wow what interesting news');
//         sleep(5);
//         $i->click("Publish news");
//         sleep(5);
//         $i->amOnUrl($home_page_url);
//         $i->canSeeElement(['css' => 'div[class=component-container\ Schoolbox_Resource_Textbox_Component_Homepage_Controller]']);
//         sleep(2);
//         $i->click("Add text and multimedia, or embed content");
//         sleep(5);
//         $i->fillField("heading", "Test Text Box Title");
//         $i->click(['css' => 'input[type=submit][value=Create\ Text\ Box]']);
//         sleep(2);
//         $content = 'wow what interesting Text box text';
//         $i->executeJS('for(var i in window.sbProps.editors) { window.sbProps.editors[i].setData(\'' . $content . '\')}');
//         sleep(2);
//         $i->scrollTo(['css' => 'div[class=component-container\ Schoolbox_Resource_Textbox_Component_Homepage_Controller]']);
//         $element = Locator::contains('div.component-action section button', 'Save');
//         $i->waitForElementVisible($element, 5);
//         $i->click($element);
//         sleep(2);
//         $i->canSee('wow what interesting Text box text');
//     }

//     /**
//      * @depends createHomepage
//      * @depends useElement
//      * @param AcceptanceTester $i
//      * @throws ModuleException
//      * @throws Exception
//      *
//      * as a student navigate to the homepage and check if they can see the components and its contents
//      *
//      */
//     public function viewElement(AcceptanceTester $i): void
//     {
//         $i->login("alaress", "alaress");
//         $i->amOnUrl($this->resourcesPageUrl);
//         $i->canSee("Test Homepage");
//         $testHomepage = Locator::contains('a.jstree-anchor', 'Test Homepage');
//         $i->click($testHomepage);
//         $i->waitForText("Homepage", 5);
//         sleep(1);
//         $i->click("a.icon-home");
//         $home_page_url = $i->getCurrentUrl();
//         $i->impersonateViaModifyUserPage('Ms Jana Reed');
//         $i->amOnUrl($home_page_url);
//         $i->canSeeElement(['css' => 'div[class=component-container\ Schoolbox_Blog_Homepage_Component_Controller]']);
//         sleep(2);
//         $i->canSee("Test Blog Title");
//         $i->canSeeElement(['css' => 'div[class=component-container\ Schoolbox_Comms_News_Component_Homepage_Controller]']);
//         sleep(2);
//         $i->canSee("Test News Title");
//         $i->canSeeElement(['css' => 'div[class=component-container\ Schoolbox_Resource_Textbox_Component_Homepage_Controller]']);
//         sleep(2);
//         $i->canSee("Test Text Box Title");
//         $i->updateInDatabase('comms_news_article', ['deleted_at' => "2020-01-15 11:22:34"], ['title' => "Test News Title"]);
//     }

//     /**
//      * @depends createHomepage
//      * @depends viewElement
//      * @param AcceptanceTester $i
//      * @throws ModuleException
//      *
//      * delete the component and checks it is no longer presented in the page
//      *
//      */
//     public function deleteElement(AcceptanceTester $i): void
//     {
//         $i->login("alaress", "alaress");
//         $i->amOnUrl($this->resourcesPageUrl);
//         $i->canSee("Test Homepage");
//         $testHomepage = Locator::contains('a.jstree-anchor', 'Test Homepage');
//         $i->click($testHomepage);
//         $i->waitForText("Homepage", 5);
//         sleep(1);
//         $i->click("a.icon-home");
//         $home_page_url = $i->getCurrentUrl();
//         $i->click(['css' => 'label[for=component-lock-toggle]']);
//         $i->canSeeElement(['css' => 'span[class=icon-unlocked]']);
//         sleep(2);
//         $i->click(['css' => 'div.component-container.Schoolbox_Blog_Homepage_Component_Controller a.icon-close[data-state=closed][title=Close]']);
//         sleep(3);
//         $i->canSeeElement(['css' => 'a[class=button\ submit]']);
//         $i->click(['css' => 'div.component-container.Schoolbox_Blog_Homepage_Component_Controller a[class=button\ submit]']);
//         sleep(1);
//         $i->cantSeeElement(['css' => 'div[class=component-container\ Schoolbox_Blog_Homepage_Component_Controller]']);
//         $i->click(['css' => 'div.component-container.Schoolbox_Comms_News_Component_Homepage_Controller a.icon-close[data-state=closed][title=Close]']);
//         sleep(3);
//         $i->canSeeElement(['css' => 'a[class=button\ submit]']);
//         $i->click(['css' => 'div.component-container.Schoolbox_Comms_News_Component_Homepage_Controller a[class=button\ submit]']);
//         sleep(1);
//         $i->cantSeeElement(['css' => 'div[class=component-container\ Schoolbox_Comms_News_Component_Homepage_Controller]']);
//         $i->click(['css' => 'div.component-container.Schoolbox_Resource_Textbox_Component_Homepage_Controller a.icon-close[data-state=closed][title=Close]']);
//         sleep(3);
//         $i->canSeeElement(['css' => 'a[class=button\ submit]']);
//         $i->click(['css' => 'div.component-container.Schoolbox_Resource_Textbox_Component_Homepage_Controller a[class=button\ submit]']);
//         sleep(2);
//         $i->cantSeeElement(['css' => 'div[class=component-container\ Schoolbox_Resource_Textbox_Component_Homepage_Controller]']);
//         echo "Not done yet";
//     }

//     /**
//      * @depends createHomepage
//      * @depends deleteElement
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * click Cancel in the dialog when attempting to delete a homepage and ensure the homepage is not deleted
//      *
//      */
//     public function deleteHomepageFail(AcceptanceTester $i): void
//     {
//         $i->login("alaress", "alaress");
//         $i->amOnUrl($this->resourcesPageUrl);
//         $i->canSee("Test Homepage");
//         $i->click("Test Homepage");
//         $i->waitForText("Delete", 5);
//         $i->click("Delete");
//         $i->waitForText("Please Confirm", 5);
//         $i->click("Cancel");
//         sleep(1);
//         $i->amOnUrl($this->resourcesPageUrl);
//         $i->canSee("Test Homepage");
//     }

//     /**
//      * @depends createHomepage
//      * @depends deleteElement
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * click Delete in the dialog when attempting to delete a homepage and ensure the homepage is deleted
//      *
//      */
//     public function deleteHomepage(AcceptanceTester $i): void
//     {
//         $i->login("alaress", "alaress");
//         $i->amOnUrl($this->resourcesPageUrl);
//         $i->canSee("Test Homepage");
//         $i->click("Test Homepage");
//         $i->waitForText("Delete", 5);
//         $i->click("Delete");
//         $i->waitForText("Please Confirm", 5);
//         $i->click("a#confirm-dialog-confirm-button.button.submit");
//         sleep(1);
//         $i->amOnUrl($this->resourcesPageUrl);
//         $i->cantSee("Test Homepage");
//     }

//     /**
//      * Soft deleting data to not impact the tests
//      *
//      * @param AcceptanceTester $i
//      */
//     public function tearDown(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
//         $i->updateInDatabase('folder', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => "Test Homepage%"]);
//         $i->updateInDatabase('blog', ['deleted_at' => "2022-01-15 11:22:34"], ['heading like' => "Test Blog Title"]);
//         $i->updateInDatabase('textBox', ['deleted_at' => "2022-01-15 11:22:34"], ['heading like' => "Test Text Box Title"]);
//         $i->updateInDatabase('comms_news_article', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => "Test News Title"]);
//     }
// }
// /**
//  * creates various textboxes, add a textbox in a homepage and keep a few hidden,
//  * logged in as different users check whether they are able to view the textboxes or not
//  * 1. Provision sales.tar.gz data
//  * 2. Execute npx playwright test textbox.spec.ts
//  */

// import { test, expect } from "@schoolboxTest/playwright/support/integrations";
// import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
// import ManageAllTextbox from "@schoolboxTest/playwright/support/page/homepage/ManageAllTextbox";
// import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
// import Resources from "@schoolboxTest/playwright/support/page/resources/Resources";
// import TextboxForm from "@schoolboxTest/playwright/support/page/homepage/TextboxForm";
// import Common from "@schoolboxTest/playwright/support/page/Common";
// import SearchPage from "@schoolboxTest/playwright/support/page/search/SearchPage";

// test.describe("Textbox Tests", () => {
//     test.beforeEach(async ({ tester }) => {
//         await tester.loginAsAdmin();
//     });

//     /**
//      * Soft deleting the homepage with certain name to not impact the test
//      */
//     test.beforeAll(async ({ baseTester, db }) => {
//         baseTester.isGoingTo("update delete_at for the textbox (folder table) so it does not impact the news tests");
//         await db.updateInDatabase("folder", { deleted_at: "2022-01-15 11:22:34" }, { name: "Test TextBox Homepage%" });
//     });

//     /**
//      * create a homepage
//      */

//     test("create a homepage", async ({ tester, page }) => {
//         const createFolder = new CreateFolder(page);
//         const folderName = "Test TextBox Homepage";
//         const homepageTemplateOption = "Blank";
//         tester.isGoingTo("create a homepage folder");
//         await tester.loginAsAdmin();
//         await createFolder.goto();
//         tester.isGoingTo("type a folder name, select a template option and set access permissions");
//         await createFolder.typeName(folderName);
//         await createFolder.setHomepageTemplate(homepageTemplateOption);
//         await createFolder.setAccessPermission(1, "write");
//         await createFolder.setAccessPermission(2, "write");
//         await createFolder.setAccessPermission(3, "read");
//         await createFolder.setAccessPermission(4, "read");
//         await createFolder.setAccessPermission(5, "read");
//         await createFolder.setAccessPermission(6, "read");
//         await createFolder.submit();
//         tester.isGoingTo("navigate to the resources page and check the new homepage folder is presented");
//         await createFolder.gotoResources();
//         await expect(page.locator("#container")).toContainText(folderName);
//     });

//     //*
//     // as alaress I will navigate to a homepage
//     // add a textbox component with heading and content
//     // and verify it is properly presented
//     // */

//     test("create a textbox", async ({ tester, page, db }) => {
//         const createFolder = new CreateFolder(page);
//         const resources = new Resources(page);
//         const manageAll = new ManageAllTextbox(tester, page);
//         const homepage = new Homepage(page);
//         const textbox = new TextboxForm(tester, page);
//         const folderName = "Test TextBox Homepage";
//         const heading = "Test Text Box Title";
//         const content = "I am textbox content";

//         tester.isGoingTo("Get the folder id");
//         const homepageId = (await db.grabFromDatabase("folder", "id", {
//             deleted_at: null,
//             "name LIKE": folderName,
//         })) as number;

//         tester.isGoingTo("Navigate to the Test TextBox Homepage via resources browser");
//         await createFolder.gotoResources();
//         await resources.clickFolderName(folderName);
//         await resources.clickMenuItem("home");

//         tester.isGoingTo("Add a new component in the homepage");
//         await homepage.addComponent("#tabs-sub-1", "Text Box");
//         tester.isGoingTo("Enter content into the new component");
//         await page.waitForSelector("i.icon-textbox");
//         const layout = Homepage.ELEMENT_COMPONENT_LAYOUT_TOP;
//         const position = 1;
//         await homepage.clickComponentEmptyStateLink(layout, position);
//         await textbox.fillHeading(heading);
//         await textbox.submit();
//         tester.isGoingTo("get the component id");
//         const textboxId = (await db.grabFromDatabase("component_instance", "id", {
//             identifier: homepageId,
//         })) as number;
//         await textbox.fillContent(content, textboxId);
//         await textbox.save();
//         tester.isGoingTo("Verify what I entered is the heading and body");
//         await expect(page.locator("#container")).toContainText(heading);
//         await expect(page.locator("#container")).toContainText(content);

//         tester.isGoingTo("Create another 2 text boxes");
//         await manageAll.clickManageAll();
//         await manageAll.clickCreateTextBox();
//         const textBoxCount = await db.countInDatabase("textBox", {
//             categoryID: homepageId,
//         });
//         await manageAll.typeTextBoxHeading(textBoxCount, "Second Text Box");
//         await manageAll.typeTextBoxContent(textBoxCount, "Test");
//         await manageAll.clickCreateTextBox();
//         const textBoxItem = textBoxCount + 1;
//         await manageAll.typeTextBoxHeading(textBoxItem, "Third Text Box");
//         await manageAll.typeTextBoxContent(textBoxItem, "Jana Reed");
//         await manageAll.clickSaveAll();
//         await tester.runShellCommand("php /usr/share/schoolbox/tools/search/buildIndex.php -p --from='-10 min'");
//     });

//     /**
//      * Verifies which textbox different users can or cannot view
//      *
//      * as teacher and student I will navigate to a homepage,
//      * check the textbox I can view
//      * and also which textboxes are presented for me when I search via search bar
//      */

//     test("textbox visibility", async ({ tester, page, db }) => {
//         const folderName = "Test TextBox Homepage";
//         const common = new Common(page);
//         const manageAll = new ManageAllTextbox(tester, page);
//         const searchPage = new SearchPage(page);
//         const homepage = new Homepage(page);
//         const textbox = new TextboxForm(tester, page);

//         tester.isGoingTo("As Jack Coles search for a  textbox and ensure it is presented in the search results grid");
//         await tester.impersonate("Jack Coles");
//         await common.search("Jana Reed");
//         await searchPage.clearFilters();
//         await searchPage.clickFilterType("textbox");
//         await searchPage.clickApplyFilters();
//         await page.waitForSelector("#search-page-container");
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Jana Reed");
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Third Text Box");
//         await searchPage.inputKeyword("Test");
//         await searchPage.clearFilters();
//         await searchPage.clickFilterType("textbox");
//         await searchPage.clickApplyFilters();
//         await page.waitForSelector("#search-page-container");
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Second Text Box");
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Test");
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Test Text Box Title");
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("I am textbox content");

//         tester.isGoingTo(
//             "As Jana Reed search for a  textbox and check whether it is presented in the search results grid or not",
//         );
//         await tester.impersonate("Jana Reed");
//         await common.search("'Jana Reed'");
//         await searchPage.clearFilters();
//         await common.clickSearchTypeCheckbox("textbox");
//         await searchPage.clickApplyFilters();
//         await page.waitForSelector("#search-page-container");
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).not.toContainText("Jana Reed");
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).not.toContainText("Third Text Box");
//         await searchPage.inputKeyword("Test");
//         await searchPage.clearFilters();
//         await searchPage.clickFilterType("textbox");
//         await searchPage.clickApplyFilters();
//         await page.waitForSelector("#search-page-container");
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).not.toContainText("Second Text Box");
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Test Text Box Title");
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("I am textbox content");

//         tester.isGoingTo("As Jack Coles navigate to the Test TextBox Homepage");
//         await tester.impersonate("Jack Coles");
//         const homepageId = (await db.grabFromDatabase("folder", "id", {
//             deleted_at: null,
//             "name LIKE": folderName,
//         })) as number;
//         await page.goto(`https://vagrant.dev.schoolbox.cloud/homepage/${homepageId}`);
//         tester.isGoingTo("Unlock the components and edit the textbox settings");
//         await homepage.clickLockToggle();
//         await homepage.clickComponentSettings();
//         await homepage.typeTextBoxHeading("Jana to be hidden");
//         tester.isGoingTo("Hide the textbox title from read only users");
//         const componentId = (await db.grabFromDatabase("component_instance", "id", {
//             identifier: homepageId,
//         })) as number;
//         await homepage.uncheckComponentCheckbox(componentId, "showComponentTitle");
//         await manageAll.clickSaveAll();
//         tester.isGoingTo("Update the textbox content");
//         await page.locator("button").getByText("Modify").click();
//         tester.isGoingTo("get the component id");
//         const textboxId = (await db.grabFromDatabase("component_instance", "id", {
//             identifier: homepageId,
//         })) as number;
//         await textbox.modify();
//         await textbox.fillContent("Jamal to be presented", textboxId);
//         await textbox.save();

//         await tester.runShellCommand("php /usr/share/schoolbox/tools/search/buildIndex.php -p --from='-10 min'");

//         tester.isGoingTo("As Jana Reed search for textboxes and check whether its is presented or not");
//         await tester.impersonate("Jana Reed");
//         await common.search("Jamal");
//         await searchPage.clearFilters();
//         await common.clickSearchTypeCheckbox("textbox");
//         await searchPage.clickApplyFilters();
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Jamal to be presented");
//         await searchPage.inputKeyword("Jana");
//         await searchPage.clearFilters();
//         await searchPage.clickApplyFilters();
//         await page.waitForSelector("#search-page-container");
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Jamal to be presented");
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Untitled textbox");
//         await expect(page.locator('div[class="l-flex-col l-gap-m"]')).not.toContainText("Jana to be hidden");
//         tester.isGoingTo("Navigate to the Test TextBox Homepage to check whether the textbox is presented or not");
//         await page.goto(`https://vagrant.dev.schoolbox.cloud/homepage/${homepageId}`);
//         await expect(page.locator("#container")).toContainText("Jamal to be presented");
//         await expect(page.locator("#container")).not.toContainText("Jana to be hidden");
//     });

//     test.afterEach(async ({ tester }) => {
//         await tester.logout();
//     });

//     /**
//      * Soft deleting data to not impact the tests
//      */
//     test.afterAll(async ({ baseTester, db }) => {
//         baseTester.isGoingTo(
//             "update delete_at for the data created in this test so it does impact next suite of tests",
//         );
//         await db.updateInDatabase("folder", { deleted_at: "2022-01-15 11:22:34" }, { name: "Test TextBox Homepage" });
//         await db.updateInDatabase("textBox", { deleted_at: "2022-01-15 11:22:34" }, { heading: "Test Text Box Title" });
//         await db.updateInDatabase("textBox", { deleted_at: "2022-01-15 11:22:34" }, { heading: "Second Text Box" });
//         await db.updateInDatabase("textBox", { deleted_at: "2022-01-15 11:22:34" }, { heading: "Third Text Box" });
//     });
// });

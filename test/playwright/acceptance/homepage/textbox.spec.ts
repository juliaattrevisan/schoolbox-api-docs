/**
 * creates various textboxes, add a textbox in a homepage and keep a few hidden,
 * logged in as different users check whether they are able to view the textboxes or not
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test textbox.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
// import Common from "@schoolboxTest/playwright/support/page/Common";
import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import ManageAllTextbox from "@schoolboxTest/playwright/support/page/homepage/ManageAllTextbox";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import Resources from "@schoolboxTest/playwright/support/page/resources/Resources";
import TextboxForm from "@schoolboxTest/playwright/support/page/homepage/TextboxForm";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
// import SearchPage from "@schoolboxTest/playwright/support/page/search/SearchPage";

test.describe("Textbox Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    /**
     * Soft deleting the homepage with certain name to not impact the test
     */
    test.beforeAll(async ({ tester, db }) => {
        tester.isGoingTo("update delete_at for the textbox (folder table) so it does not impact the news tests");
        await db.updateInDatabase("folder", { deleted_at: "2022-01-15 11:22:34" }, { name: "Test TextBox Homepage%" });
    });

    /**
     * create a homepage
     */

    test("create a homepage", async ({ tester, page }) => {
        const createFolder = new CreateFolder(page);
        const folderName = "Test TextBox Homepage";
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
        await expect(page.locator("#container")).toContainText(folderName);
    });

    //*
    // as alaress I will navigate to a homepage,
    // add a textbox component with heading and content
    // and verify it is properly presented
    // */

    test("create a textbox", async ({ tester, page, db }) => {
        const createFolder = new CreateFolder(page);
        const resources = new Resources(page);
        const manageAll = new ManageAllTextbox(page);
        const assessmentForm = new AssessmentForm(page);
        // const homepage = new Homepage(page);
        const textbox = new TextboxForm(page);
        const folderName = "Test TextBox Homepage";
        const heading = "Test Text Box Title";
        const content = "I am textbox content";

        tester.isGoingTo("Navigate to the Test TextBox Homepage via resources browser");
        await createFolder.gotoResources();
        await resources.clickFolderName(folderName);
        await resources.clickMenuItem("home");

        tester.isGoingTo("Add a new component in the homepage");
        // await homepage.addComponent("#tabs-sub-1", "Text Box");
        // tester.isGoingTo("Enter content into the new component");
        // await page.waitForSelector("i.icon-textbox");
        // const layout = "top";
        // const position = 1;
        // await homepage.clickComponentEmptyStateLink(layout, position);
        await expect(page.locator('a[data-reveal-id="addTextboxModal-4901"]')).toBeVisible();
        await page.click('a[data-reveal-id="addTextboxModal-4901"]');
        await textbox.fillHeading(heading);
        await textbox.submit();
        await assessmentForm.setDescription(content);
        // await textbox.fillContent(content);
        await textbox.save();

        tester.isGoingTo("Verify what I entered is the heading and body");
        await expect(page.locator("#container")).toContainText(heading);
        await expect(page.locator("#container")).toContainText(content);

        tester.isGoingTo("Create another 2 text boxes");
        await manageAll.clickManageAll();
        await manageAll.clickCreateTextBox();
        const homepageId = (await db.grabFromDatabase("folder", "id", {
            deleted_at: null,
            "name LIKE": folderName,
        })) as number;
        const textBoxCount = await db.countInDatabase("textBox", {
            categoryID: homepageId,
        });
        await manageAll.typeTextBoxHeading(textBoxCount, "Second Text Box");
        await manageAll.typeTextBoxContent(textBoxCount, "Test");
        await manageAll.clickCreateTextBox();
        const textBoxItem = textBoxCount + 1;
        await manageAll.typeTextBoxHeading(textBoxItem, "Third Text Box");
        await manageAll.typeTextBoxContent(textBoxItem, "Jana Reed");
        await manageAll.clickSaveAll();
        await tester.runShellCommand("php /usr/share/schoolbox/tools/search/buildIndex.php -p --from='-10 min'");
    });

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });
});

// use Tests\Support\AcceptanceTester;
// use Tests\Support\Page\Common;
// use Tests\Support\Page\Folder\CreateFolder;
// use Tests\Support\Page\Homepage;
// use Exception;
// use Tests\Support\Page\Homepage\ManageAllTextbox;
// use Tests\Support\Page\Resources\Resources;
// use Tests\Support\Page\Search\SearchPage;

// /**
//  * creates various textboxes, add a textbox in a homepage and keep a few hidden,
//  * logged in as different users check whether they are able to view the textboxes or not
//  * 1. Provision sales.tar.gz data
//  * 2. vagrant ssh
//  * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance Homepage/TextboxCest
//  */
// class TextboxCest
// {
//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      * @return void
//      */
//     public function _before(AcceptanceTester $i): void
//     {
//         $i->logout();
//     }

//     /**
//      * @param AcceptanceTester $i
//      */

//     /**
//      * Soft deleting the homepage with certain name to not impact the test
//      *
//      * @param AcceptanceTester $i
//      */
//     public function beforeSuite(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("update delete_at for the textbox (folder table) so it does not impact the news tests");
//         $i->updateInDatabase('folder', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => "Test TextBox Homepage%"]);
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @return void
//      * @throws Exception
//      *
//      * create a homepage
//      *
//      */
//     public function createHomepage(AcceptanceTester $i): void
//     {
//         $i->loginAsAdmin();
//         $createFolder = new createFolder($i);
//         $i->amGoingTo("create a homepage folder");
//         $i->amOnPage($createFolder::URL);
//         $createFolder->waitCreateFolderTitleToBePresented();
//         $folderName = "Test TextBox Homepage";
//         $homepageTemplateOption = "Blank";
//         $i->amGoingTo("type a folder name, select a template option and set access permissions");
//         $createFolder->typeName($folderName);
//         $createFolder->setHomepageTemplate($homepageTemplateOption);
//         $createFolder->setAccessPermission('groupWrite1');
//         $createFolder->setAccessPermission('groupWrite2');
//         $createFolder->setAccessPermission('groupRead3');
//         $createFolder->setAccessPermission('groupRead4');
//         $createFolder->setAccessPermission('groupRead5');
//         $createFolder->setAccessPermission('groupRead6');
//         $createFolder->clickCreateFolderButton();
//         $i->amGoingTo("navigate to the resources page and check the new homepage folder is presented");
//         $i->amOnPage($createFolder::URL_RESOURCES);
//         $i->canSee("Test TextBox Homepage");
//     }

//     /**
//      *
//      * as alaress I will navigate to a homepage,
//      * add a textbox component with heading and content
//      * and verify it is properly presented
//      *
//      * @param AcceptanceTester     $i
//      * @param Homepage             $homepage
//      * @param Homepage\TextboxForm $textboxForm
//      * @depends createHomepage
//      *
//      * @depends createHomepage
//      * @throws Exception
//      */
//     public function createTextbox(AcceptanceTester $i, Homepage $homepage, Homepage\TextboxForm $textboxForm): void
//     {
//         $i->loginAsAdmin();
//         $createFolder = new createFolder($i);
//         $resources = new Resources($i);
//         $manageAll = new ManageAllTextbox($i);
//         $folderName = "Test TextBox Homepage";

//         $i->amGoingTo("Navigate to the Test TextBox Homepage via resources browser");
//         $i->amOnPage($createFolder::URL_RESOURCES);
//         $i->waitForText($folderName);
//         $resources->clickFolderName($folderName);
//         $i->waitForText("Homepage", 5);
//         $resources->clickMenuItem('home');

//         $i->amGoingTo("Add a new component in the homepage");
//         $homepage->addComponent(Homepage::ELEMENT_ADD_COMPONENT_SECTION_INFORMATION, 'Text Box');
//         $i->amGoingTo("Enter content into the new component");
//         $i->waitForElement('i.icon-textbox');
//         $layout   = Homepage::ELEMENT_COMPONENT_LAYOUT_TOP;
//         $position = 1;
//         $homepage->clickComponentEmptyStateLink($layout, $position);
//         sleep(2);
//         $heading = 'Test Text Box Title';
//         $content = 'I am textbox content';
//         $textboxForm
//             ->fillHeading($heading)
//             ->submit()
//             ->fillContent($content)
//             ->save();

//         $i->amGoingTo("Verify what I entered is the heading and body");
//         $i->canSee($heading);
//         $i->canSee($content);

//         $i->amGoingTo("Create another 2 text boxes");
//         $manageAll->clickManageAll();
//         $manageAll->clickCreateTextBox();
//         $homepageId = $i->grabFromDatabase('folder', 'id', ['deleted_at' => null,'name like' => $folderName]);
//         $textBoxCount = $i->grabNumRecords('textBox', ['categoryID' => $homepageId]);
//         $manageAll->typeTextBoxHeading($textBoxCount, 'Second Text Box');
//         $manageAll->typeTextBoxContent($textBoxCount, 'Test');
//         $manageAll->clickCreateTextBox();
//         $textBoxItem = $textBoxCount + 1;
//         $manageAll->typeTextBoxHeading($textBoxItem, 'Third Text Box');
//         $manageAll->typeTextBoxContent($textBoxItem, 'Jana Reed');
//         $manageAll->clickSaveAll();
//         exec('php /usr/share/schoolbox/tools/search/buildIndex.php -p --from="-10 min";');
//     }

//     /**
//      * Verifies which textbox different users can or cannot view
//      *
//      * as teacher and student I will navigate to a homepage,
//      * check the textbox I can view
//      * and also which textboxes are presented for me when I search via search bar
//      *
//      * @param AcceptanceTester     $i
//      * @param Homepage             $homepage
//      * @param Homepage\TextboxForm $textboxForm

//      * @throws Exception
//      */
//     public function textboxVisibility(AcceptanceTester $i, Homepage $homepage, Homepage\TextboxForm $textboxForm): void
//     {
//         $i->loginAsAdmin();
//         $folderName = "Test TextBox Homepage";
//         $common = new Common($i);
//         $manageAll = new ManageAllTextbox($i);
//         $searchPage = new SearchPage($i);

//         $i->amGoingTo("As Jack Coles search for a  textbox and ensure it is presented in the search results grid");
//         $i->impersonate('Jack Coles');
//         $common->search('Jana Reed');
//         $searchPage->clearFilters();
//         $common->clickSearchTypeCheckbox('textbox');
//         $searchPage->clickApplyFilters();
//         $i->waitForElementVisible('div.alert-box');
//         $i->canSee('Jana Reed', 'mark');
//         $i->canSee('Third Text Box');
//         $common->search('Test');
//         $searchPage->clearFilters();
//         $common->clickSearchTypeCheckbox('textbox');
//         $searchPage->clickApplyFilters();
//         $i->waitForElementVisible('div.alert-box');
//         $i->canSee('Second Text Box', '//h6/a');
//         $i->canSee('Test', 'mark');
//         $i->canSee('Test Text Box Title', '//h6/a');
//         $i->canSee('I am textbox content', '//article/p');

//         $i->amGoingTo("As Jana Reed search for a  textbox and check whether it is presented in the search results grid or not");
//         $i->impersonate('Jana Reed');
//         $common->search("'Jana Reed'");
//         $searchPage->clearFilters();
//         $common->clickSearchTypeCheckbox('textbox');
//         $searchPage->clickApplyFilters();
//         $i->amGoingTo('ensure Third Text Box is not presented');
//         $i->cantSee('Jana Reed', 'mark');
//         $i->cantSee('Third Text Box', '//h6/a');
//         $common->search('Test');
//         $searchPage->clearFilters();
//         $common->clickSearchTypeCheckbox('textbox');
//         $searchPage->clickApplyFilters();
//         $i->waitForElementVisible('div.alert-box');
//         $i->cantSee('Second Text Box', '//h6/a');
//         $i->canSee('Test Text Box Title', '//h6/a');
//         $i->canSee('I am textbox content', '//article/p');

//         $i->amGoingTo("As Jack Coles navigate to the Test TextBox Homepage");
//         $i->impersonate('Jack Coles');
//         $homepageId = $i->grabFromDatabase('folder', 'id', ['deleted_at' => null,'name like' => $folderName]);
//         $i->amOnPage('/homepage/' . $homepageId);
//         $i->amGoingTo("Unlock the components and edit the textbox settings");
//         $homepage->clickLockToggle();
//         $homepage->clickComponentSettings();
//         $homepage->typeTextBoxHeading('Jana to be hidden');
//         $i->amGoingTo("Hide the textbox title from read only users");
//         $componentId = $i->grabFromDatabase('component_instance', 'id', ['identifier' => $homepageId]);
//         $homepage->uncheckComponentCheckbox($componentId, 'showComponentTitle');
//         $manageAll->clickSaveAll();
//         $i->amGoingTo("Update the textbox content");
//         $i->click('Modify');
//         $textboxForm->fillContent('Jamal to be presented');
//         $textboxForm->save();
//         exec('php /usr/share/schoolbox/tools/search/buildIndex.php -p --from="-10 min";');

//         $i->amGoingTo("As Jana Reed search for textboxes and check whether its is presented or not");
//         $i->impersonate('Jana Reed');
//         $common->search('Jamal');
//         $searchPage->clearFilters();
//         $common->clickSearchTypeCheckbox('textbox');
//         $searchPage->clickApplyFilters();
//         $i->canSee('Jamal to be presented', '//article/p');
//         $common->search('Jana');
//         $searchPage->clickApplyFilters();
//         $i->amGoingTo('expect to see the textbox with Untitled textbox header');
//         $i->canSee('Jamal to be presented');
//         $i->canSee('Untitled textbox', '//h6/a');
//         $i->cantSee('Jana to be hidden');
//         $i->amGoingTo("Navigate to the Test TextBox Homepage to check whether the textbox is presented or not");
//         $i->amOnPage('/homepage/' . $homepageId);
//         $i->canSee('Jamal to be presented');
//         $i->cantSee('Jana to be hidden');
//     }

//     /**
//      * Soft deleting data to not impact the tests
//      *
//      * @param AcceptanceTester $i
//      */
//     public function tearDown(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
//         $i->updateInDatabase('folder', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => "Test TextBox Homepage"]);
//         $i->updateInDatabase('textBox', ['deleted_at' => "2022-01-15 11:22:34"], ['heading like' => "Test Text Box Title"]);
//         $i->updateInDatabase('textBox', ['deleted_at' => "2022-01-15 11:22:34"], ['heading like' => "Second Text Box"]);
//         $i->updateInDatabase('textBox', ['deleted_at' => "2022-01-15 11:22:34"], ['heading like' => "Third Text Box"]);
//     }
// }
// /**
//  * This test creates various groups with different group permissions
//  * check whether the group is presented or not for the user that is logged in
//  * 1. Provision sales.tar.gz data
//  * 2. Execute npx playwright test groupPermission.spec.ts
//  */

// import { test, expect } from "@schoolboxTest/playwright/support/integrations";
// import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
// import ManageGroup from "@schoolboxTest/playwright/support/page/group/ManageGroup";
// import Resources from "@schoolboxTest/playwright/support/page/resources/Resources";

// test.describe("Group Permission Tests", () => {
//     test.beforeEach(async ({ tester }) => {
//         await tester.loginAsAdmin();
//     });

//     test("Group Free", async ({ tester, page, db }) => {
//         const createFolder = new CreateFolder(page);
//         const manageGroup = new ManageGroup(page);
//         const folderName = "Group Free";
//         const homepageTemplateOption = "Course";
//         const studentJunior = "Ms Eden Reed";
//         const studentMiddle = "Louisa Smith";
//         const studentSenior = "Mr Tim Reed";
//         const groupMember = "Ms Eloise White";
//         await createFolder.goto();

//         tester.isGoingTo("create a group with write permission to teachers");
//         await createFolder.typeName(folderName);
//         await createFolder.setHomepageTemplate(homepageTemplateOption);
//         await createFolder.setAccessPermission(1, "write");
//         await createFolder.setAccessPermission(2, "write");
//         tester.isGoingTo("set write permission to senior student");
//         await createFolder.setAccessPermission(3, "write");
//         await createFolder.setAccessPermission(4, "write");
//         tester.isGoingTo("set write permission to junior student");
//         await createFolder.setAccessPermission(5, "read");
//         tester.isGoingTo("set none permission to middle student");
//         await createFolder.setAccessPermission(6, "none");
//         await createFolder.clickCreateGroupToggle();
//         await createFolder.submit();
//         const folderId = (await db.grabFromDatabase("folder", "id", {
//             deleted_at: null,
//             "name LIKE": folderName,
//         })) as number;
//         expect(folderId).not.toBe(null);
//         await manageGroup.setAccessPermission("type-3");
//         await manageGroup.clickUpdateButton();
//         tester.isGoingTo("add Eloise White into the group");
//         await manageGroup.search("elwhite");
//         await manageGroup.selectUser(50);
//         await manageGroup.clickFromRadioButton("role");
//         await manageGroup.clickAddMembers();

//         tester.isGoingTo("get logged in as a junior student to check the group is presented to them");
//         await tester.impersonate(studentJunior);
//         await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
//         await expect(page.locator("#container")).toContainText("Group Free");

//         tester.isGoingTo("get logged in as a middle student to check the group is NOT presented to them");
//         await tester.impersonateViaModifyUserPage(studentMiddle);
//         await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
//         await expect(page.locator("#container")).not.toContainText("Group Free");

//         tester.isGoingTo("get logged in as a senior student to check the group is presented to them");
//         await tester.impersonateViaModifyUserPage(studentSenior);
//         await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
//         await expect(page.locator("#container")).toContainText("Group Free");

//         tester.isGoingTo("get logged in as Eloise White and check she is able to leave the group");
//         await tester.impersonateViaModifyUserPage(groupMember);
//         await page.goto(`https://vagrant.dev.schoolbox.cloud/homepage/${folderId}`);
//         await page.click("[data-test=sbx-pop-out-tab-control] [data-test=sbx-pop-out-tab-control-button]");
//         await expect(page.locator("#container")).toContainText("Group Free");
//         await page.getByText("Leave Group").click();
//         await page.click("[data-test=sbx-pop-out-tab-control] [data-test=sbx-pop-out-tab-control-button]");
//         await expect(page.locator("#container")).toContainText("Join Group");
//     });

//     /**
//      * create groups with permissions that allow users to join the groups
//      * folder: read/group moderated
//      * folder: none/group moderated
//      * folder: write/group moderated
//      * get logged in as that users and check whether that groups are presented to join or not
//      * table with scenarios described in 13387
//      */
//     test("Group Moderated", async ({ tester, page, db }) => {
//         const createFolder = new CreateFolder(page);
//         const manageGroup = new ManageGroup(page);
//         const folderName = "Group Moderated";
//         const homepageTemplateOption = "Course";
//         const studentJunior = "Ms Eden Reed";
//         const studentMiddle = "Louisa Smith";
//         const studentSenior = "Mr Tim Reed";
//         const groupMember = "Ms Eloise White";
//         await createFolder.goto();

//         tester.isGoingTo("create a group with write permission to teachers");
//         await createFolder.typeName(folderName);
//         await createFolder.setHomepageTemplate(homepageTemplateOption);
//         await createFolder.setAccessPermission(1, "write");
//         await createFolder.setAccessPermission(2, "write");
//         tester.isGoingTo("set write permission to senior student");
//         await createFolder.setAccessPermission(3, "write");
//         await createFolder.setAccessPermission(4, "write");
//         tester.isGoingTo("set read permission to junior student");
//         await createFolder.setAccessPermission(5, "read");
//         tester.isGoingTo("set none permission to middle student");
//         await createFolder.setAccessPermission(6, "none");
//         await createFolder.clickCreateGroupToggle();
//         await createFolder.submit();
//         const folderId = (await db.grabFromDatabase("folder", "id", {
//             deleted_at: null,
//             "name LIKE": folderName,
//         })) as number;
//         expect(folderId).not.toBe(null);
//         await manageGroup.setAccessPermission("type-2");
//         await manageGroup.clickUpdateButton();
//         tester.isGoingTo("add Jana Reed into the group");
//         await manageGroup.search("elwhite");
//         await manageGroup.selectUser(50);
//         await manageGroup.clickFromRadioButton("role");
//         await manageGroup.clickAddMembers();

//         tester.isGoingTo("get logged in as a junior student to check the group is presented to them");
//         await tester.impersonate(studentJunior);
//         await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
//         await expect(page.locator("#container")).toContainText("Group Moderated");

//         tester.isGoingTo("get  logged in as a middle student to check the group is NOT presented to them");
//         await tester.impersonateViaModifyUserPage(studentMiddle);
//         await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
//         await expect(page.locator("#container")).not.toContainText("Group Moderated");

//         tester.isGoingTo("get logged in as a senior student to check the group is presented to them");
//         await tester.impersonateViaModifyUserPage(studentSenior);
//         await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
//         await expect(page.locator("#container")).toContainText("Group Moderated");

//         tester.isGoingTo("get logged in as Eloise White and check she is able to leave the group");
//         await tester.impersonateViaModifyUserPage(groupMember);
//         await page.goto(`https://vagrant.dev.schoolbox.cloud/homepage/${folderId}`);
//         await page.click("[data-test=sbx-pop-out-tab-control] [data-test=sbx-pop-out-tab-control-button]");
//         await expect(page.locator("#container")).toContainText("Leave Group");
//         await page.getByText("Leave Group").click();
//         await page.click("[data-test=sbx-pop-out-tab-control] [data-test=sbx-pop-out-tab-control-button]");
//         await expect(page.locator("#container")).not.toContainText("Cancel Application");
//     });

//     /**
//      * create groups with permissions that allow users to join the groups
//      * folder: read/group moderated
//      * folder: none/group moderated
//      * folder: write/group moderated
//      * get logged in as that users and check whether that groups are presented to join or not
//      * table with scenarios described in 13387
//      */
//     test("Group Restricted", async ({ tester, page, db }) => {
//         const createFolder = new CreateFolder(page);
//         const manageGroup = new ManageGroup(page);
//         const folderName = "Group Restricted";
//         const homepageTemplateOption = "Course";
//         const studentJunior = "Ms Eden Reed";
//         const studentMiddle = "Louisa Smith";
//         const studentSenior = "Mr Tim Reed";
//         const groupMember = "Ms Eloise White";
//         await createFolder.goto();

//         tester.isGoingTo("create a group with write permission to teachers");
//         await createFolder.typeName(folderName);
//         await createFolder.setHomepageTemplate(homepageTemplateOption);
//         await createFolder.setAccessPermission(1, "write");
//         await createFolder.setAccessPermission(2, "write");
//         tester.isGoingTo("set write permission to senior student");
//         await createFolder.setAccessPermission(3, "write");
//         await createFolder.setAccessPermission(4, "write");
//         tester.isGoingTo("set read permission to junior student");
//         await createFolder.setAccessPermission(5, "read");
//         tester.isGoingTo("set none permission to middle student");
//         await createFolder.setAccessPermission(6, "none");
//         await createFolder.clickCreateGroupToggle();
//         await createFolder.submit();
//         const folderId = (await db.grabFromDatabase("folder", "id", {
//             deleted_at: null,
//             "name LIKE": folderName,
//         })) as number;
//         expect(folderId).not.toBe(null);

//         await manageGroup.setAccessPermission("type-1");
//         await manageGroup.clickUpdateButton();
//         tester.isGoingTo(`add Jana Reed into the group`);
//         await manageGroup.search("jreed");
//         await manageGroup.selectUser(25);
//         await manageGroup.clickFromRadioButton("role");
//         await manageGroup.clickAddMembers();

//         tester.isGoingTo("get logged in as a junior student to check the group is NOT presented to them");
//         await tester.impersonate(studentJunior);
//         await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
//         await expect(page.locator("#container")).not.toContainText("Group Restricted");

//         tester.isGoingTo("get  logged in as a middle student to check the group is NOT presented to them");
//         await tester.impersonateViaModifyUserPage(studentMiddle);
//         await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
//         await expect(page.locator("#container")).not.toContainText("Group Restricted");

//         tester.isGoingTo("get logged in as a senior student to check the group is NOT presented to them");
//         await tester.impersonateViaModifyUserPage(studentSenior);
//         await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
//         await expect(page.locator("#container")).not.toContainText("Group Restricted");

//         tester.isGoingTo("get logged in as Jana Reed and check she is able to leave the group");
//         await tester.impersonateViaModifyUserPage(groupMember);
//         await page.goto(`https://vagrant.dev.schoolbox.cloud/homepage/${folderId}`);
//         await page.click("[data-test=sbx-pop-out-tab-control] [data-test=sbx-pop-out-tab-control-button]");
//         await expect(page.locator("#container")).not.toContainText("Leave Group");
//     });

//     /**
//      * create a group with Jack Coles as a admin
//      * create a sub folder to that group
//      * access the sub folder as Jack Coles
//      * access the sub folder as a Jane Reed to see access denied
//      */
//     test("inherit group permission", async ({ tester, page, db }) => {
//         const folderName = "Group Root";
//         const homepageTemplateOption = "Course";
//         const subFolderName = "Group 1";
//         const createFolder = new CreateFolder(page);
//         const manageGroup = new ManageGroup(page);
//         const resources = new Resources(page);
//         await createFolder.goto();

//         tester.isGoingTo("create a group with write permission to teachers");
//         await createFolder.typeName(folderName);
//         await createFolder.setHomepageTemplate(homepageTemplateOption);
//         await createFolder.setAccessPermission(1, "none");
//         await createFolder.setAccessPermission(2, "none");
//         tester.isGoingTo("set none permission to senior student");
//         await createFolder.setAccessPermission(3, "none");
//         await createFolder.setAccessPermission(4, "none");
//         tester.isGoingTo("set none permission to junior student");
//         await createFolder.setAccessPermission(5, "none");
//         tester.isGoingTo("set none permission to middle student");
//         await createFolder.setAccessPermission(6, "none");
//         await createFolder.clickCreateGroupToggle();
//         await createFolder.submit();

//         await manageGroup.setAccessPermission("type-1");
//         await manageGroup.clickUpdateButton();

//         tester.isGoingTo("add Jack Coles into the group");
//         await manageGroup.search("jcoles");
//         await manageGroup.selectUser(22);
//         await manageGroup.clickFromRadioButton("role");
//         await manageGroup.clickAddMembers();
//         tester.isGoingTo("Making Jack Coles the group admin via database");
//         const folderId = (await db.grabFromDatabase("folder", "id", {
//             deleted_at: null,
//             "name LIKE": folderName,
//         })) as number;
//         expect(folderId).not.toBe(null);
//         await db.updateInDatabase("userAccess", { type: 3 }, { sid: folderId });

//         tester.isGoingTo("create a sub folder to the group");
//         await createFolder.gotoResources();
//         await resources.clickFolderName(folderName);
//         await resources.clickMenuItem("folder");
//         await createFolder.typeName(subFolderName);
//         await createFolder.setHomepageTemplate(homepageTemplateOption);
//         await createFolder.submit();

//         tester.isGoingTo("Impersonate Jack Coles and access the sub folder");
//         await tester.impersonate("Jack Coles");
//         await createFolder.gotoResources();
//         await resources.clickFolderName(folderName);
//         await resources.clickFolderName(subFolderName);
//         await resources.clickMenuItem("home");
//         await expect(page.locator("#container")).toContainText("Group 1");
//         await expect(page.locator("#container")).not.toContainText("You do not have permission to view this page");

//         tester.isGoingTo("Impersonate Jane Reed and access the sub folder");
//         await tester.impersonate("Jana Reed");
//         await page.goto(`https://vagrant.dev.schoolbox.cloud/homepage/${folderId}`);
//         await expect(page.locator("#container")).not.toContainText("Group 1");
//         await expect(page.locator("#container")).toContainText("You do not have permission to view this page");
//     });

//     test.afterEach(async ({ tester }) => {
//         await tester.logout();
//     });

//     /**
//      * Soft-delete data is about to start so it does not get impacted by other tests already executed
//      */
//     test.afterAll(async ({ baseTester, db }) => {
//         baseTester.isGoingTo(
//             "update delete_at for the data created in this test so it does impact next suite of tests",
//         );
//         await db.updateInDatabase("folder", { deleted_at: "2022-01-15 11:22:34" }, { "name LIKE": "Group Free%" });
//         await db.updateInDatabase("folder", { deleted_at: "2022-01-15 11:22:34" }, { "name LIKE": "Group Moderated%" });
//         await db.updateInDatabase(
//             "folder",
//             { deleted_at: "2022-01-15 11:22:34" },
//             { "name LIKE": "Group Restricted%" },
//         );
//         await db.updateInDatabase("folder", { deleted_at: "2022-01-15 11:22:34" }, { "name LIKE": "Group Root%" });
//         await db.updateInDatabase("folder", { deleted_at: "2022-01-15 11:22:34" }, { "name LIKE": "Group 1" });
//     });
// });

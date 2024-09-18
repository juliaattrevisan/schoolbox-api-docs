import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";
import PastoralProfile from "@schoolboxTest/playwright/support/page/pastoralCare/PastoralProfile";
import CreatePastoralRecord from "@schoolboxTest/playwright/support/page/pastoralCare/CreatePastoralRecord";
import Common from "@schoolboxTest/playwright/support/page/Common";

/**
 * creates a pastoral record, check the pastoral record details, comments it,
 * deletes the comment, modify the record, delete the record
 *
 * Steps:
 * 1. Provision sales data
 * 2. Execute npx playwright test PastoralRecords.spec.ts
 *
 */
test.describe("Pastoral Records", () => {
    /**
     * Preparations
     *
     * get logged in as admin
     */
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    /**
     * Deleting data when the test is about to start so it does not get impacted by other tests already executed
     * @param tester
     * @param db
     */
    test.beforeAll(async ({ tester, db }) => {
        tester.isGoingTo("update delete_at for the pastoral records so it does not impact the tests");
        await db.updateInDatabase(
            "pastoral",
            { deleted_at: "2022-01-15 11:22:34" },
            { heading: "Automation Test Pastoral Record%" },
        );
        await db.updateInDatabase(
            "pastoral",
            { deleted_at: "2022-01-15 11:22:34" },
            { heading: "Automation Test View Pastoral Record%" },
        );
        await db.updateInDatabase(
            "pastoral",
            { deleted_at: "2022-01-15 11:22:34" },
            { heading: "This is an automation pastoral record updated%" },
        );
        await db.updateInDatabase("config", { value: 1 }, { name: "pastoral_student_access" });
    });

    /**
     *
     * navigate to the student pastoral care page
     * create a pastoral record for the student
     * ensure the record is created with the proper details
     */
    test("Create Pastoral Record", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const pastoralProfile = new PastoralProfile(tester, page);
        const createPastoralRecord = new CreatePastoralRecord(tester, page);
        const common = new Common(page);
        const studentId = "25";
        const pastoralTitle = "Automation Test Pastoral Record";
        const pastoralDescription = "This is an automation pastoral record";
        tester.isGoingTo("navigate to the student profile page");
        await profilePage.goto(studentId);
        tester.isGoingTo("click the pastoral care button");
        await profilePage.clickButton("pastoral");
        tester.isGoingTo("click create pastoral record and create a pastoral record");
        await pastoralProfile.clickCreatePastoralRecord();
        await createPastoralRecord.typeTitle(pastoralTitle);
        await createPastoralRecord.clickTypeDropdown();
        await createPastoralRecord.selectTypeSubType("1");
        const body = page.locator(`#body_public`);
        await tester.fillCKEditorByLocator(body, pastoralDescription);
        await createPastoralRecord.clickVisibilityLink("Role");
        await createPastoralRecord.clickPastoralRole("Administration");
        await common.closeModal();
        await createPastoralRecord.clickSave();
        tester.isGoingTo("ensure the record is presented under pastoral records");
        await pastoralProfile.goto(studentId);
        // await expect(page.locator("#pastoralRecords")).toContainText("PASTORAL PROFILE");
        await expect(page.locator("#pastoralRecords")).toContainText(pastoralTitle);
    });

    /**
     * navigate to the student pastoral care page
     * comment a pastoral record for the student
     * ensure the comment is created with the proper details
     * delete the comment
     * ensure the comment is deleted
     */

    test("Comment Pastoral Record", async ({ tester, page, db }) => {
        const pastoralProfile = new PastoralProfile(tester, page);
        const common = new Common(page);
        const studentId = "25";
        const pastoralComment = "This is an automation pastoral comment";
        tester.isGoingTo("navigate to the student pastoral profile page");
        await pastoralProfile.goto(studentId);
        tester.isGoingTo("click the pastoral record");
        const recordId = (await db.grabFromDatabase("pastoral", "id", {
            deleted_at: null,
            "heading like": "Automation Test Pastoral Record%",
        })) as number;
        await pastoralProfile.clickPastoralRecord(25, recordId);
        await common.clickAddComment();
        await tester.fillCKEditorById("comment-public", pastoralComment);
        await pastoralProfile.clickPostComment();
        tester.isGoingTo("check the comment is presented");
        // await tester.wait(2);
        await expect(page.locator("#pastoral-record-comments-container-public")).toContainText(pastoralComment);
        tester.isGoingTo("delete the pastoral comment");
        await pastoralProfile.clickDeleteCommentIcon();
        await common.clickModalDeleteButton();
        // await tester.wait(2);
        await expect(page.locator("#pastoral-record-comments-container-public")).not.toContainText(pastoralComment);
    });
});

// <?php

// /**
//  * @copyright   Copyright 2023, Schoolbox Pty Ltd
//  * @link        https://schoolbox.com.au/
//  */

// declare(strict_types=1);

// namespace Tests\Acceptance\PastoralCare;

// use Tests\Support\AcceptanceTester;
// use Exception;
// use Tests\Support\Page\Common;
// use Tests\Support\Page\ProfilePage\ProfilePage;
// use Tests\Support\Page\PastoralCare\CreatePastoralRecord;
// use Tests\Support\Page\PastoralCare\PastoralProfile;

// /**
//  * creates a pastoral record, check the pastoral record details, comments it,
//  * deletes the comment, modify the record, delete the record
//  *
//  * Steps:
//  * 1. Provision legacy.tar.gz data
//  * 2. vagrant ssh
//  * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance Pastoral/PastoralRecordsCest
//  *
//  */

// class PastoralRecordsCest
// {
//     /**
//      * Preparations
//      *
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * get logged in as admin
//      */
//     public function _before(AcceptanceTester $i): void
//     {
//         $i->logout();
//         $i->loginAsAdmin();
//     }

//     /**
//      * Deleting data when the test is about to start so it does not get impacted by other tests already executed
//      *
//      * @param AcceptanceTester $i
//      */
//     public function beforeSuite(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("update delete_at for the pastoral records so it does not impact the tests");
//         $i->updateInDatabase('pastoral', ['deleted_at' => "2022-01-15 11:22:34"], ['heading like' => "Automation Test Pastoral Record%"]);
//         $i->updateInDatabase('pastoral', ['deleted_at' => "2022-01-15 11:22:34"], ['heading like' => "Automation Test View Pastoral Record%"]);
//         $i->updateInDatabase('pastoral', ['deleted_at' => "2022-01-15 11:22:34"], ['heading like' => "This is an automation pastoral record updated%"]);
//         $i->updateInDatabase('config', ['value' => 1], ['name' => 'pastoral_student_access']);
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * navigate to the student pastoral care page
//      * create a pastoral record for the student
//      * ensure the record is created with the proper details
//      */
//     public function createPastoralRecord(AcceptanceTester $i): void
//     {
//         $profilePage = new ProfilePage($i);
//         $pastoralProfile = new PastoralProfile($i);
//         $createPastoralRecord = new CreatePastoralRecord($i);
//         $common = new Common($i);
//         $studentId = 25;
//         $pastoralTitle = "Automation Test Pastoral Record";
//         $pastoralDescription = 'This is an automation pastoral record';
//         $i->amGoingTo('navigate to the student profile page');
//         $i->amOnPage($profilePage->generateRoute($studentId));
//         $i->amGoingTo('click the pastoral care button');
//         $profilePage->clickButton('pastoral');
//         $i->waitForText('PASTORAL PROFILE', 10);
//         $i->amGoingTo('click create pastoral record and create a pastoral record');
//         $pastoralProfile->clickCreatePastoralRecord();
//         $createPastoralRecord->typeTitle($pastoralTitle);
//         $createPastoralRecord->clickTypeDropdown();
//         $createPastoralRecord->selectTypeSubType(1);
//         $i->fillRichTextField('body_public', $pastoralDescription);
//         $createPastoralRecord->clickVisibilityLink('Role');
//         $createPastoralRecord->clickPastoralRole('Administration');
//         $common->closeModal();
//         $createPastoralRecord->clickSave();
//         $i->amGoingTo('ensure the record is presented under pastoral records');
//         $i->amOnPage($pastoralProfile->generateRoute($studentId));
//         $i->waitForText('PASTORAL PROFILE', 10);
//         $i->canSee($pastoralTitle, '//div/a/h3');
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      * @depends createPastoralRecord
//      *
//      * navigate to the student pastoral care page
//      * comment a pastoral record for the student
//      * ensure the comment is created with the proper details
//      * delete the comment
//      * ensure the comment is deleted
//      */
//     public function commentPastoralRecord(AcceptanceTester $i): void
//     {
//         $pastoralProfile = new PastoralProfile($i);
//         $common = new Common($i);
//         $studentId = 25;
//         $pastoralComment = 'This is an automation pastoral comment';
//         $i->amGoingTo('navigate to the student pastoral profile page');
//         $i->amOnPage($pastoralProfile->generateRoute($studentId));
//         $i->waitForText('PASTORAL PROFILE', 10);
//         $i->amGoingTo('click the pastoral record');
//         $recordId = $i->grabFromDatabase('pastoral', 'id', ['deleted_at' => null, 'heading like' => "Automation Test Pastoral%"]);
//         $pastoralProfile->clickPastoralRecord($studentId, $recordId);
//         $i->waitForElementVisible('a[id="panel-general"]', 10);
//         $common->clickAddComment();
//         $i->waitForElementVisible(['css' => '#comment-public + div.ck'], 5);
//         $i->fillCkEditorById('comment-public', $pastoralComment);
//         $pastoralProfile->clickPostComment();
//         $i->amGoingTo('check the comment is presented');
//         $i->wait(2);
//         $i->canSee('This is an automation pastoral comment', '//article/p');
//         $i->amGoingTo('delete the pastoral comment');
//         $pastoralProfile->clickDeleteCommentIcon();
//         $common->clickModalDeleteButton();
//         $i->wait(2);
//         $i->cantSee($pastoralComment);
//     }
//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      * @depends createPastoralRecord
//      * navigate to the student pastoral care page
//      * modify a pastoral record for the student
//      * ensure the record is updated with the proper details
//      */
//     public function modifyPastoralRecord(AcceptanceTester $i): void
//     {
//         $pastoralProfile = new PastoralProfile($i);
//         $common = new Common($i);
//         $createPastoralRecord = new CreatePastoralRecord($i);
//         $studentId = 25;
//         $pastoralTitle = 'This is an automation pastoral record updated';
//         $i->amGoingTo('navigate to the pastoral record and click the modify button');
//         $i->amOnPage($pastoralProfile->generateRoute($studentId));
//         $recordId = $i->grabFromDatabase('pastoral', 'id', ['deleted_at' => null, 'heading like' => "Automation Test Pastoral%"]);
//         $pastoralProfile->clickPastoralRecord($studentId, $recordId);
//         $i->waitForElementVisible('a[id="panel-general"]', 10);
//         $common->clickIconModifyPage();
//         $i->amGoingTo('get the pastoral record updated');
//         $createPastoralRecord->typeTitle($pastoralTitle);
//         $createPastoralRecord->clickTypeDropdown();
//         $createPastoralRecord->selectTypeSubType(2);
//         $createPastoralRecord->clickSave();
//         $i->amGoingTo('ensure the record are properly updated');
//         $i->amOnPage($pastoralProfile->generateRoute($studentId));
//         $i->waitForText('PASTORAL PROFILE', 10);
//         $i->canSee($pastoralTitle, '//div/a/h3');
//         $i->amGoingTo('click the pastoral record');
//         $recordId = $i->grabFromDatabase('pastoral', 'id', ['deleted_at' => null, 'heading like' => "This is an automation pastoral record updated%"]);
//         $pastoralProfile->clickPastoralRecord($studentId, $recordId);
//         $i->waitForElementVisible('a[id="panel-general"]', 10);
//         $i->canSee('Attendance', '//div/dl/dd');
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      * @depends modifyPastoralRecord
//      * navigate to the student pastoral care page
//      * delete a pastoral record for the student
//      * ensure the record is no longer presented
//      */
//     public function deletePastoralRecord(AcceptanceTester $i): void
//     {
//         $pastoralProfile = new PastoralProfile($i);
//         $common = new Common($i);
//         $studentId = 25;
//         $i->amGoingTo('navigate to the pastoral profile page and delete a pastoral record');
//         $i->amOnPage($pastoralProfile->generateRoute($studentId));
//         $pastoralTitle = 'This is an automation pastoral record updated';
//         $recordId = $i->grabFromDatabase('pastoral', 'id', ['deleted_at' => null, 'heading like' => $pastoralTitle]);
//         $pastoralProfile->clickDeleteIcon($recordId);
//         $common->clickConfirmButton();
//         $i->amGoingTo('ensure the record is no longer presented');
//         $i->wait(2);
//         $i->cantSee($pastoralTitle);
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * navigate to the student pastoral care page
//      * create a pastoral record for the student
//      * ensure the record details is properly presented in the pastoral record page
//      */
//     public function viewPastoralRecord(AcceptanceTester $i): void
//     {
//         $profilePage = new ProfilePage($i);
//         $pastoralProfile = new PastoralProfile($i);
//         $createPastoralRecord = new CreatePastoralRecord($i);
//         $common = new Common($i);
//         $studentId = 25;
//         $pastoralTitle = "Automation Test View Pastoral Record";
//         $pastoralDescription = 'This is an automation pastoral record';
//         $i->amGoingTo('navigate to the student profile page');
//         $i->amOnPage($profilePage->generateRoute($studentId));
//         $i->amGoingTo('click the pastoral care button');
//         $profilePage->clickButton('pastoral');
//         $i->waitForText('PASTORAL PROFILE', 10);
//         $i->amGoingTo('click create pastoral record and create a pastoral record  visible to parents and students');
//         $pastoralProfile->clickCreatePastoralRecord();
//         $createPastoralRecord->typeTitle($pastoralTitle);
//         $createPastoralRecord->clickTypeDropdown();
//         $createPastoralRecord->selectTypeSubType(1);
//         $i->fillRichTextField('body_public', $pastoralDescription);
//         $createPastoralRecord->clickVisibilityLink('Role');
//         $createPastoralRecord->clickPastoralRole('Student');
//         $createPastoralRecord->clickPastoralRole('Parent');
//         $common->closeModal();
//         $createPastoralRecord->clickSave();
//         $i->amGoingTo('ensure the record is presented under pastoral records');
//         $i->amOnPage($pastoralProfile->generateRoute($studentId));
//         $i->waitForText('PASTORAL PROFILE', 10);
//         $i->canSee($pastoralTitle, '//div/a/h3');
//         $i->amGoingTo('as a parent check the pastoral care records details');
//         $i->impersonateViaModifyUserPage('Ms Jenni Reed');
//         $i->amOnPage($pastoralProfile->generateRoute($studentId));
//         $recordId = $i->grabFromDatabase('pastoral', 'id', ['deleted_at' => null, 'heading like' => "Automation Test View Pastoral%"]);
//         $pastoralProfile->clickPastoralRecord($studentId, $recordId);
//         $i->waitForElementVisible('a[id="panel-general"]', 10);
//         $i->canSee('Automation Test View Pastoral Record');
//         $i->canSee('Academic', '//div/dl/dd');
//         $i->canSee('Award/Achievement', '//div/dl/dd');
//         $i->amGoingTo('as a student check the pastoral care records details');
//         $i->impersonateViaModifyUserPage('Ms Jana Reed');
//         $i->amOnPage($pastoralProfile->generateRoute($studentId));
//         $pastoralProfile->clickPastoralRecord($studentId, $recordId);
//         $i->waitForElementVisible('a[id="panel-general"]', 10);
//         $i->canSee('Automation Test View Pastoral Record');
//         $i->canSee('Academic', '//div/dl/dd');
//         $i->canSee('Award/Achievement', '//div/dl/dd');
//     }

//     /**
//      * Soft deleting data to not impact the tests
//      * settinig up data to its original value in the database
//      *
//      * @param AcceptanceTester $i
//      */
//     public function tearDown(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
//         $i->updateInDatabase('pastoral', ['deleted_at' => "2022-01-15 11:22:34"], ['heading like' => "Automation Test Pastoral Record%"]);
//         $i->updateInDatabase('pastoral', ['deleted_at' => "2022-01-15 11:22:34"], ['heading like' => "Automation Test View Pastoral Record%"]);
//         $i->updateInDatabase('pastoral', ['deleted_at' => "2022-01-15 11:22:34"], ['heading like' => "This is an automation pastoral record updated%"]);
//         $i->updateInDatabase('config', ['value' => 0], ['name' => 'pastoral_student_access']);
//     }
// }

// /**
//  * This test adds, updates and deletes a badge
//  * Steps:
//  * 1. Provision sales.tar.gz data
//  * 2. Execute npx playwright test badges.spec.ts
//  */

// import { test, expect } from "@schoolboxTest/playwright/support/integrations";
// import Badges from "@schoolboxTest/playwright/support/page/badges/Badges";
// import CreateBadge from "@schoolboxTest/playwright/support/page/badges/CreateBadge";

// test.describe("Badges tests", () => {
//     /**
//      * Deleting Bagades when the test is about to start so it does not get impacted by other tests already executed
//      */
//     test.beforeAll(async ({ tester, db }) => {
//         tester.isGoingTo("update delete_at for the news article so it does not impact the news tests");
//         await db.updateInDatabase("badge", { deleted_at: "2022-01-15 11:22:34" }, { "name like": "Automation Badge%" });
//     });

//     test.beforeEach(async ({ tester }) => {
//         await tester.loginAsAdmin();
//     });

//     /**
//      * on learning/badge page click Create Badge button
//      * Type a Name
//      * Select a badge image via modal
//      * Select a category
//      * Click Create Badge
//      * Ensure the Badge is presented in the /learning/badge screen
//      */
//     test("Create badge", async ({ tester, page }) => {
//         const badges = new Badges(tester, page);
//         const createBadge = new CreateBadge(tester, page);

//         const badgeName = "Automation Badge";
//         const category = "Learning";
//         tester.isGoingTo("learning badges page");
//         await badges.goto();

//         tester.isGoingTo("click Create Badge button and create a badge");
//         await badges.clickAddBadgeButton();
//         await createBadge.typeName(badgeName);
//         await createBadge.clickBrowseIconLibraryButton();
//         await createBadge.waitModalDialogPresented();
//         await createBadge.clickVerticalTabItem("food");
//         await createBadge.clickBadgeImage("food/1");
//         await createBadge.clickCategoryDropDown();
//         await createBadge.clickCategoryItem(category);
//         await createBadge.clickCreateBadgeButton();

//         tester.isGoingTo("ensure the badge is presented");
//         await badges.waitAddBadgeButtonToBePresented();
//         await expect(page.locator("#container")).toContainText(badgeName);
//         await expect(page.locator("#container")).toContainText(category);
//     });

//     /**
//      * on /learning/badge select Modify badge
//      * update the title
//      * update the category
//      * Click Modify Badge
//      * Ensure the Badge title and category are properly presented in the /learning/badge screen
//      */
//     test("Modify badge", async ({ tester, page, db }) => {
//         const badges = new Badges(tester, page);
//         const createBadge = new CreateBadge(tester, page);

//         const category = "Learning";
//         tester.isGoingTo("click the badge dropdown and click Modify");
//         await badges.goto();
//         const badgeId = (await db.grabFromDatabase("badge", "id", {
//             deleted_at: null,
//             "name like": "Automation Badge",
//         })) as number;
//         await badges.clickBadgeDropdown(badgeId);
//         await badges.clickDropdownModify(badgeId);

//         tester.isGoingTo("get name and category updated and click Modify Badge button");
//         await createBadge.typeName("Automation Badge Updated");
//         await createBadge.clickCategoryDropDown();
//         await createBadge.clickCategoryItem(category);
//         await createBadge.clickCreateBadgeButton();
//         await badges.waitAddBadgeButtonToBePresented();
//         await expect(page.locator("#container")).toContainText("Automation Badge Updated");
//         await expect(page.locator("#container")).toContainText(category);
//     });

//     /**
//      * on /learning/badge click the Badge dropdown
//      * Click Delete and confirm it
//      * Ensure the Badge is no longer presented in the /learning/badge screen
//      */
//     test("Delete badge", async ({ tester, page, db }) => {
//         const badges = new Badges(tester, page);

//         tester.isGoingTo("click the badge dropdown and click Delete");
//         await badges.goto();
//         const badgeId = (await db.grabFromDatabase("badge", "id", {
//             deleted_at: null,
//             "name like": "Automation Badge Updated",
//         })) as number;
//         await badges.clickBadgeDropdown(badgeId);
//         await badges.clickDropdownDelete();
//         await badges.clickConfirm();
//         await badges.goto();

//         tester.isGoingTo("ensure the badge is no longer presented in the page");
//         await expect(page.locator("#badge-list-container")).not.toContainText("Automation Badge Updated");

//         tester.isGoingTo("reload the page to ensue the badge is not presented");
//         await badges.goto();
//         await expect(page.locator("#badge-list-container")).not.toContainText("Automation Badge Updated");
//     });

//     /**
//      * Soft deleting data to not impact the tests
//      */
//     test.afterAll(async ({ tester, db }) => {
//         tester.isGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
//         await db.updateInDatabase("badge", { deleted_at: "2022-01-15 11:22:34" }, { "name like": "Automation Badge%" });
//     });
// });

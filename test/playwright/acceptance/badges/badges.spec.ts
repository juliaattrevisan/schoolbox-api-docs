/**
 * This test adds, updates and deletes a badge
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test badges.spec.ts
 *  */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Badges from "@schoolboxTest/playwright/support/page/badges/Badges";
import CreateBadge from "@schoolboxTest/playwright/support/page/badges/CreateBadge";

test.describe("Badges tests", () => {
    /**
     * Deleting Bagdes when the test is about to start so it does not get impacted by other tests already executed
     */
    test.beforeAll(async ({ tester, db }) => {
        tester.isGoingTo("update delete_at for the news article so it does not impact the news tests");
        await db.updateInDatabase("badge", { deleted_at: "2022-01-15 11:22:34" }, { "name like": "Automation Badge%" });
    });

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    /**
     * on learning/badge page click Create Badge button
     * Type a Name
     * Select a badge image via modal
     * Select a category
     * Click Create Badge
     * Ensure the Badge is presented in the /learning/badge screen
     */
    test("Create badge", async ({ tester, page }) => {
        const badges = new Badges(tester, page);
        const createBadge = new CreateBadge(tester, page);

        const badgeName = "Automation Badge";
        const category = "Learning";
        tester.isGoingTo("learning badges page");
        await badges.goto();

        tester.isGoingTo("click Create Badge button and create a badge");
        await badges.clickAddBadgeButton();
        await createBadge.typeName(badgeName);
        await createBadge.clickBrowseIconLibraryButton();
        await createBadge.waitModalDialogPresented();
        await createBadge.clickVerticalTabItem("food");
        await createBadge.clickBadgeImage("food/1");
        await createBadge.clickCategoryDropDown();
        await createBadge.clickCategoryItem(category);
        await createBadge.clickCreateBadgeButton();

        tester.isGoingTo("ensure the badge is presented");
        await badges.waitAddBadgeButtonToBePresented();
        await expect(page.locator("#container")).toContainText(badgeName);
        await expect(page.locator("#container")).toContainText(category);
    });
});

// <?php

// /**
//  * @copyright   Copyright 2022, Schoolbox Pty Ltd
//  * @link        https://schoolbox.com.au/
//  */

// declare(strict_types=1);

// namespace Tests\Acceptance\Badges;

// use Tests\Support\AcceptanceTester;
// use Tests\Support\Page\Badges\Badges;
// use Tests\Support\Page\Badges\CreateBadge;
// use Exception;

// /**
//  * This test adds, updates and deletes a badge
//  * Steps:
//  * 1. Provision sales.tar.gz data
//  * 2. vagrant ssh
//  * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance Badges/BadgesCest
//  */
// class BadgesCest
// {
//     /**
//      * Preparations
//      *
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * get logged in
//      * navigate to https://vagrant.dev.schoolbox.cloud/learning/badge
//      */
//     public function _before(AcceptanceTester $i): void
//     {
//         $i->logout();
//         $i->loginAsAdmin();
//         $badges = new Badges($i);
//         $i->amOnPage($badges::URL);
//     }

//     /**
//      * Deleting Bagdes when the test is about to start so it does not get impacted by other tests already executed
//      *
//      * @param AcceptanceTester $i
//      */
//     public function beforeSuite(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("update delete_at for the news article so it does not impact the news tests");
//         $i->updateInDatabase('badge', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => "Automation Badge%"]);
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on learning/badge page click Create Badge button
//      * Type a Name
//      * Select a badge image via modal
//      * Select a category
//      * Click Create Badge
//      * Ensure the Badge is presented in the /learning/badge screen
//      */
//     public function createBadge(AcceptanceTester $i): void
//     {
//         $badges = new Badges($i);
//         $createBadge = new CreateBadge($i);
//         $badgeName = "Automation Badge";
//         $category = "Learning";
//         $i->amGoingTo('click Create Badge button and create a badge');
//         $badges->clickAddBadgeButton();
//         $createBadge->typeName($badgeName);
//         $createBadge->clickBrowseIconLibraryButton();
//         $createBadge->waitModalDialogPresented();
//         $createBadge->clickVerticalTabItem("food");
//         $createBadge->clickBadgeImage("food/1");
//         $createBadge->clickCategoryDropDown();
//         $createBadge->clickCategoryItem($category);
//         $createBadge->clickCreateBadgeButton();
//         $i->amGoingTo('ensure the badge is presented');
//         $badges->waitAddBadgeButtonToBePresented();
//         $badges->verifyTextIsPresented($badgeName);
//         $badges->verifyTextIsPresented($category);
//     }

//     /**
//      * @depends createBadge
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on /learning/badge select Modify badge
//      * update the title
//      * update the category
//      * Click Modify Badge
//      * Ensure the Badge title and category are properly presented in the /learning/badge screen
//      */
//     public function modifyBadge(AcceptanceTester $i): void
//     {
//         $badges = new Badges($i);
//         $createBadge = new CreateBadge($i);
//         $category = "Learning";
//         $i->amGoingTo('click the badge dropdown and click Modify');
//         $badgeId = $i->grabFromDatabase('badge', 'id', ['deleted_at' => null,'name like' => 'Automation Badge']);
//         $badges->clickBadgeDropdown($badgeId);
//         $badges->clickDropdownModify($badgeId);
//         $i->amGoingTo('get name and category updated and click Modify Badge button');
//         $createBadge->typeName('Automation Badge Updated');
//         $createBadge->clickCategoryDropDown();
//         $createBadge->clickCategoryItem($category);
//         $createBadge->clickCreateBadgeButton();
//         $badges->waitAddBadgeButtonToBePresented();
//         $badges->verifyTextIsPresented('Automation Badge Updated');
//         $badges->verifyTextIsPresented($category);
//     }

//     /**

//      * @param AcceptanceTester $i
//      * @throws Exception
//      * @depends modifyBadge
//      * on /learning/badge click the Badge dropdown
//      * Click Delete and confirm it
//      * Ensure the Badge is no longer presented in the /learning/badge screen
//      */
//     public function deleteBadge(AcceptanceTester $i): void
//     {
//         $badges = new Badges($i);
//         $i->amGoingTo('click the badge dropdown and click Delete');
//         $badgeId = $i->grabFromDatabase('badge', 'id', ['deleted_at' => null,'name like' => 'Automation Badge Updated']);
//         $badges->clickBadgeDropdown($badgeId);
//         $badges->clickDropdownDelete();
//         $badges->clickConfirm();
//         $i->amGoingTo('ensure the badge is no longer presented in the page');
//         $i->cantSee('Automation Badge Updated');
//         $i->amGoingTo('reload the page to ensue the badge is not presented');
//         $i->amOnPage($badges::URL);
//         $i->cantSee('Automation Badge Updated');
//     }

//     /**
//      * Soft deleting data to not impact the tests
//      *
//      * @param AcceptanceTester $i
//      */
//     public function tearDown(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
//         $i->updateInDatabase('badge', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => "Automation Badge%"]);
//     }
// }
// /**
//  * This test imports the course into a class, copies an activity into a class
//  */
// import { test, expect } from "@schoolboxTest/playwright/support/integrations";
// import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
// import CourseModify from "@schoolboxTest/playwright/support/page/courses/CourseModify";
// import CourseList from "@schoolboxTest/playwright/support/page/courses/CourseList";
// import ImportCourseIntoClass from "@schoolboxTest/playwright/support/page/courses/ImportCourseIntoClass";

// test.describe("Course import tests", () => {
//     /**
//      * Deleting data is about to start so it does not get impacted by other tests already executed
//      */
//     test.beforeAll(async ({ baseTester, db }) => {
//         baseTester.isGoingTo("update delete_at so data does not impact the tests");
//         await db.updateInDatabase(
//             "folder",
//             { deleted_at: "2022-01-15 11:22:34" },
//             { "name LIKE": "Automation Course Import Folder%" },
//         );
//         await db.updateInDatabase("folder_code", { deleted_at: "2022-01-15 11:22:34" }, { "code LIKE": "test%" });
//     });

//     test.beforeEach(async ({ tester }) => {
//         await tester.loginAsAdmin();
//     });

//     /**
//      * Import a course into a class
//      * - navigate to /resource/folder/0/create
//      * - create a folder
//      * - navigate to /course/list
//      * - import year 9 English into the homepage created above in the before function
//      * - export only romeo and Juliet
//      * - untick a quiz, due work and task before importing
//      * - ensure the course is presented in the homepage
//      */
//     test("Import course into class", async ({ tester, page, db }) => {
//         const createFolder = new CreateFolder(page);
//         const courseList = new CourseList(page);
//         const importCourseIntoClass = new ImportCourseIntoClass(page);

//         const folderName = `Automation Course Import Folder1`;
//         const homepageTemplateOption = "Course";
//         const schoolCode = "test1";

//         tester.isGoingTo(`create a folder named "${folderName}"`);
//         await createFolder.goto();
//         await createFolder.typeName(folderName);
//         await createFolder.setHomepageTemplate(homepageTemplateOption);

//         tester.isGoingTo("set write access permission for Administration and Teacher");
//         await createFolder.setAccessPermission(1, "write");
//         await createFolder.setAccessPermission(2, "write");
//         tester.isGoingTo("set read access permission for senior student");
//         await createFolder.setAccessPermission(3, "read");
//         tester.isGoingTo("set read access permission for parent");
//         await createFolder.setAccessPermission(4, "read");
//         tester.isGoingTo("set read access permission for junior student");
//         await createFolder.setAccessPermission(5, "read");
//         tester.isGoingTo("set read access permission for middle student");
//         await createFolder.setAccessPermission(6, "read");

//         tester.isGoingTo("type a school code and click Create button");
//         await createFolder.typeClassCode(schoolCode);
//         await createFolder.submit();

//         tester.isGoingTo("navigate to the course list page");
//         await courseList.goto();

//         tester.isGoingTo("click the 3-dot menu for Year 9 English course in the course list page");
//         await courseList.clickCourseDotMenu(6);
//         tester.isGoingTo("click the option Import into classes from the dropdown menu");
//         await courseList.clickImportIntoClass(6);

//         tester.isGoingTo("type a class name, select it from the results and click the Next button");
//         await importCourseIntoClass.typeClassName(folderName);
//         await importCourseIntoClass.clickClassName();
//         await importCourseIntoClass.clickSubmit("Next");

//         tester.isGoingTo("tick Romeo and Juliet unit checkbox, fill open and close dates and hit Next");
//         await importCourseIntoClass.tickUnitCheckbox(1);

//         const openDate = new Date();
//         openDate.setDate(openDate.getDate() - 2); // 2 days ago
//         const dueDate = new Date();
//         dueDate.setDate(dueDate.getDate() + 30); // 30 days from now
//         await importCourseIntoClass.fillOpenDate(1, openDate);
//         await importCourseIntoClass.insertDueDate(1, dueDate);
//         await importCourseIntoClass.clickSubmit("Next");

//         tester.isGoingTo("untick Romeo and Juliet Key Quote Activity in the step 3 Coursework");
//         await importCourseIntoClass.tickAssessmentCheckbox(52);
//         tester.isGoingTo("untick Present a scene of Romeo and Juliet in the step 3 Coursework");
//         await importCourseIntoClass.tickAssessmentCheckbox(146);
//         tester.isGoingTo("untick Post-Unit Quiz in the step 3 Coursework");
//         await importCourseIntoClass.tickAssessmentCheckbox(151);

//         tester.isGoingTo(
//             "click button Import, navigate to the homepage and check that Romeo and Juliet unit is presented in the page",
//         );
//         await importCourseIntoClass.clickSubmit("Import");

//         await new Promise((resolve) => {
//             setTimeout(resolve, 3000);
//         }); // Wait for import to complete

//         const folderId = (await db.grabFromDatabase("folder", "id", { name: folderName, deleted_at: null })) as string;
//         await page.goto(`/homepage/${folderId}`);

//         await expect(page.locator("#container")).toContainText("Romeo and Juliet"); // TODO: Use data-test
//     });

//     /**
//      * Copy activity into a class
//      * - navigate to /course/modify/6
//      * - copy activity into a class for the unticked quiz, due work and quiz above
//      * - fill the dates fields and click export to selected class
//      * - ensure the assessment is presented in that class
//      */
//     test("Copy activity into class", async ({ tester, page, db }) => {
//         const courseModify = new CourseModify(page);

//         const folderId = (await db.grabFromDatabase("folder", "id", {
//             name: "Automation Course Import Folder1",
//             deleted_at: null,
//         })) as string;

//         tester.isGoingTo("navigate to Year 9 English Modify course page");
//         await courseModify.goto(6);

//         tester.isGoingTo("click the arrow to expand Romeo and Juliet unit");
//         await courseModify.clickUnitArrow(15);

//         tester.isGoingTo("click the export icon for Romeo and Juliet Key Quote Activity");
//         await courseModify.clickExportIcon(52);

//         tester.isGoingTo("tick the checkbox");
//         const courseImplementationId = (await db.grabFromDatabase("course_implementation", "id", {
//             folder_id: folderId,
//         })) as string;
//         const importId = (await db.grabFromDatabase("course_unit_implementation", "id", {
//             course_implementation_id: courseImplementationId,
//         })) as string;
//         await page.locator(`label[for=import-${importId}]`).dispatchEvent("click"); // Must dispatch as click() can't directly target the ::before pseudo-element

//         tester.isGoingTo("click export the selected classes button");
//         await page.click("input[type=submit]");

//         tester.isGoingTo("click the export icon for Present a scene of Romeo and Juliet");
//         await courseModify.clickExportIcon(146);

//         tester.isGoingTo("tick the checkbox");
//         await page.locator(`label[for=import-${importId}]`).dispatchEvent("click"); // Must dispatch as click() can't directly target the ::before pseudo-element

//         tester.isGoingTo("click export the selected classes button");
//         await page.click("input[type=submit]");

//         tester.isGoingTo("click the export icon for Post-Unit Quiz");
//         await courseModify.clickExportIcon(151);

//         tester.isGoingTo("tick the checkbox");
//         await page.locator(`label[for=import-${importId}]`).dispatchEvent("click"); // Must dispatch as click() can't directly target the ::before pseudo-element

//         tester.isGoingTo("click export the selected classes button");
//         await page.click("input[type=submit]");

//         tester.isGoingTo(
//             "navigate to the class homepage to check if the assessments are presented in the course outline",
//         );
//         await page.goto(`/homepage/${folderId}`);

//         await expect(page.locator("#container")).toContainText("Romeo and Juliet Key Quote Activity"); // TODO: Use data-test
//         await expect(page.locator("#container")).toContainText("Present a scene of Romeo and Juliet"); // TODO: Use data-test
//         await expect(page.locator("#container")).toContainText("Post-Unit Quiz"); // TODO: Use data-test
//     });

//     test.afterEach(async ({ tester }) => {
//         await tester.logout();
//     });

//     test.afterAll(async ({ baseTester, db }) => {
//         // Soft-delete data to not interfere with other tests
//         baseTester.isGoingTo(
//             "update delete_at for the data created in this test so it does not impact next suite of tests",
//         );

//         await db.updateInDatabase(
//             "folder",
//             { deleted_at: "2022-01-15 11:12:34" },
//             { "name LIKE": "Automation Course Import Folder%" },
//         );
//         await db.updateInDatabase("folder_code", { deleted_at: "2022-01-15 11:12:34" }, { "code LIKE": "test%" });
//     });
// });

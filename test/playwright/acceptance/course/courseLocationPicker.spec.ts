// <?php

// /**
//  * @author    Andy Ryan <andy.ryan@alaress.com.au>
//  * @copyright Copyright 2019, Schoolbox Pty Ltd
//  * @link      https://schoolbox.com.au/
//  */

// namespace Tests\Acceptance;

// use Tests\Support\AcceptanceTester;
// use Tests\Support\Page\CourseModify;
// use Exception;

// /**
//  *
//  * modify the course Faculty/Subject folder and ensure the location is saved:
//  * ensure user is able to select homepage as course location and not able to select courses or units as course location,
//  * ensure it is still possible to save it for course with legacy invalid(unit) location
//  *
//  * Steps:
//  * 1. Provision sales.tar.gz data
//  * 2. vagrant ssh
//  * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance CourseLocationPickerCest
//  */
// class CourseLocationPickerCest
// {
//     /**
//      * Go to the course modify page before every test
//      *
//      * @param AcceptanceTester $i
//      * @throws Exception
//      */
//     public function _before(AcceptanceTester $i): void
//     {
//         $i->amGoingTo('Set the location of the ELC course to Competing ideologies unit');
//         $i->updateInDatabase('folder', ['parent' => 266], ['id' => 65]);
//         $i->loginAsAdmin();
//         $i->amGoingTo('navigate to the ELC course modify page');
//         $i->amOnPage(CourseModify::getUrl(4));
//         $i->amGoingTo('click the Faculty filed to be able to select a faculty / subject folder via modal');
//         $i->click(CourseModify::SELECTOR_LOCATION);
//         $i->waitForElement(CourseModify::SELECTOR_USE_FOLDER); // Need to wait for modal fade-in to finish
//     }

//     /**
//      * Clean up after every test
//      *
//      * @param AcceptanceTester $i
//      */
//     public function _after(AcceptanceTester $i): void
//     {
//         $i->amGoingTo('Set the location of the ELC course to Competing ideologies unit');
//         $i->updateInDatabase('folder', ['parent' => 266], ['id' => 65]);
//     }

//     /**
//      * Test that user can select current (invalid) location
//      *
//      * @param AcceptanceTester $i
//      */
//     public function retainCurrentInvalid(AcceptanceTester $i): void
//     {
//         $i->amGoingTo('check that Use This Folder button is initially disabled');
//         $i->seeElement(CourseModify::SELECTOR_USE_FOLDER . '[disabled]');

//         $i->amGoingTo('click current location Competing ideologies unit');
//         $i->click('[id="266"]');

//         $i->amGoingTo('check that Use This Folder button is now enabled');
//         $i->seeElement(CourseModify::SELECTOR_USE_FOLDER . ':not([disabled])');

//         $i->amGoingTo('click Use This Folder button');
//         $i->click(CourseModify::SELECTOR_USE_FOLDER);

//         $i->amGoingTo('check if the hidden form field retains this value');
//         $val = $i->executeJS("return $('#parent').val()");
//         $i->assertEquals(266, $val);

//         $i->amGoingTo('click the Save Course button');
//         $i->click(CourseModify::SELECTOR_SAVE_COURSE);

//         $i->amGoingTo('check the course has the proper faculty / subject folder');
//         $i->seeInDatabase('course_specification', ['id' => 4, 'folder_id' => 65]);
//         $i->seeInDatabase('folder', ['id' => 65, 'parent' => 266]);
//     }

//     /**
//      * Tests user:
//      * * can see other courses
//      * * cannot select courses as a course location
//      * * cannot see units inside a course node
//      * * can select a homepage as a course location
//      * * can persist a homepage as a course location
//      *
//      * @param AcceptanceTester $i
//      * @throws Exception
//      */
//     public function locationSelection(AcceptanceTester $i): void
//     {
//         $i->amGoingTo('open the French homepage node');
//         $i->scrollAndClick('[id="10"]');

//         $i->amGoingTo('check the French homepage is selected');
//         $i->assertEquals(true, $i->executeJS("return $('#10 > .jstree-wholerow.jstree-wholerow-clicked').length"));

//         $i->amGoingTo('user can see Year 9 French course node');
//         $i->see('Year 9 French');

//         $i->amGoingTo('click Year 9 French course node');
//         $i->click('[id="16"]');

//         $i->amGoingTo('check Year 9 French is not selected');
//         $i->assertEquals(false, $i->executeJS("return $('#16 > .jstree-wholerow.jstree-wholerow-clicked').length"));

//         $i->amGoingTo('check the user cannot see units inside Year 9 French course');
//         $i->assertEquals(true, $i->executeJS("return $('#16.jstree-leaf').length"));

//         $i->amGoingTo('check the French homepage still is selected');
//         $i->assertEquals(true, $i->executeJS("return $('#10 > .jstree-wholerow.jstree-wholerow-clicked').length"));

//         $i->amGoingTo('click English homepage');
//         $i->scrollAndClick('[id="85"]');

//         $i->amGoingTo('click Use This Folder button');
//         $i->click(CourseModify::SELECTOR_USE_FOLDER);

//         $i->amGoingTo('check the form has updated with this ID');
//         $i->assertEquals(85, $i->executeJS("return $('#parent').val()"));

//         $i->amGoingTo('lick Save Course button');
//         $i->click(CourseModify::SELECTOR_SAVE_COURSE);

//         $i->amGoingTo('check the database is updated to reflect the new course location');
//         $i->seeInDatabase('folder', ['id' => 65, 'parent' => 85]);
//     }

//     /**
//      * updating data to its original value
//      *
//      * @param AcceptanceTester $i
//      */
//     public function tearDown(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
//         $i->updateInDatabase('folder', ['parent' => 2], ['id' => 65]);
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

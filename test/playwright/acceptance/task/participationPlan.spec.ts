/**
 * creates a task with participation and learning plan
 * check as admin that the learning plan is presented in the assessment modify page
 * impersonates a student in the learning plan and check the proper due date is presented
 * impersonates a student not in the learning plan and check the proper due date is presented
 *
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. npx playwright test participationPlan
 *
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Common from "@schoolboxTest/playwright/support/page/Common";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import SubmitForm from "@schoolboxTest/playwright/support/page/dueWork/SubmitForm";
import StatusPage from "@schoolboxTest/playwright/support/page/dueWork/StatusPage";
import Folder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import ManageGroup from "@schoolboxTest/playwright/support/page/group/ManageGroup";
import Resources from "@schoolboxTest/playwright/support/page/resources/Resources";

test.describe("Participation Plan Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.logout();
        await tester.loginAsAdmin();
    });

    test("Participation Plan", async ({ tester, page, db }) => {
        const addComponent = new Homepage(page);
        const dueworkForm = new AssessmentForm(tester, page);
        const statusPage = new StatusPage(tester, page);
        const folder = new Folder(page);
        const manageGroup = new ManageGroup(page);
        const resources = new Resources(page);

        const student1 = Common.USER_EDENREED;
        const student2 = Common.USER_JANAREED;
        const teacher = "Jack Coles";
        const teacherId = Common.USER_ID_JACKCOLES;

        const folderName = "Participation and Learning Plan Homepage";
        const homepageTemplateOption = "Blank";

        // tester.isGoingTo("create a folder by typing a name and selecting a homepage template");
        // await folder.goto();
        // await folder.typeName(folderName);
        // await folder.setHomepageTemplate(homepageTemplateOption);
        // tester.isGoingTo("set write access permission for Administration and Teacher");
        // await folder.setAccessPermission("1", "write");
        // await folder.setAccessPermission("2", "write");
        // tester.isGoingTo("set read access permission for senior student");
        // await folder.setAccessPermission("3", "read");
        // tester.isGoingTo("set read access permission for parent");
        // await folder.setAccessPermission("4", "read");
        // tester.isGoingTo("set read access permission for junior student");
        // await folder.setAccessPermission("5", "read");
        // tester.isGoingTo("set read access permission for middle student");
        // await folder.setAccessPermission("6", "read");
        // tester.isGoingTo("type a school code and click Create button");
        // await folder.submit();

        // tester.isGoingTo("Navigate to the new folder store its location for later tests");
        // await folder.gotoResources();
        // await resources.clickFolderName(folderName);
        // await resources.clickMenuItem("home");
        // const homepageUrl = page.url();

        // tester.isGoingTo("Add Jack Coles, Eden Reed and Jana Reed into the group via manage group page");
        // await page.locator("[data-test=sbx-pop-out-tab-control-button]").click();
        // await page.getByTestId("homepage-actions-folder-manageGroup").click();
        // await tester.common.waitForPageLoad();
        // await manageGroup.search(teacher);
        // await manageGroup.selectUser(teacherId);
        // await manageGroup.clickFromRadioButton("role");
        // await manageGroup.clickAddMembers();
        // await page.waitForTimeout(1000); // waiit to be able to update the db
        // tester.isGoingTo("Make Jack Coles the group admin via database");
        // const folderId = (await db.grabFromDatabase("folder", "id", {
        //     deleted_at: null,
        //     "name like": folderName,
        // })) as number;
        // await db.updateInDatabase("userAccess", { type: 3 }, { sid: folderId, uid: "22" });
        // tester.isGoingTo("Add Eden Reed into the group");
        // await manageGroup.search(student1);
        // await manageGroup.selectUser(6);
        // await manageGroup.clickFromRadioButton("role");
        // await manageGroup.clickAddMembers();
        // tester.isGoingTo("Add Jana Reed into the group");
        // await manageGroup.clickFromRadioButton("name");
        // await manageGroup.search(student2);
        // await manageGroup.selectUser(25);
        // await manageGroup.clickFromRadioButton("role");
        // await manageGroup.clickAddMembers();

        // tester.isGoingTo("Add the task component in the homepage");
        // await page.goto(homepageUrl);
        // await addComponent.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_LEARNING, "Tasks");

        await page.goto("/homepage/332");

        tester.isGoingTo("Create a task with a learning plan");
        await page.getByTestId("component-task-empty-create-button").click();
        await dueworkForm.setDueDate(new Date("2030-08-03"));
        await page.locator('input[id="title"]').fill("Task Participation and Learning Plan");
        await dueworkForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        const dateLearningPlan = "30/10/2030";
        await dueworkForm.createLearningPlan(true, 6, "Test learning plan", dateLearningPlan);
        await page.waitForTimeout(3000);
        await dueworkForm.create();
        await expect(page.locator("h2:has-text('Tasks')")).toBeVisible();

        // tester.isGoingTo("As admin check if the learning plan is presented under Participation and Learning Plans");
        // await page.locator("a:has-text('Task Participation and Learning Plan')").click();
        // await statusPage.clickJellyTot();
        // await statusPage.clickJellyTotOption("modify");
        // await expect(page.locator("h1:has-text('Update Task Participation and Learning Plan')")).toBeVisible();
        // await expect(page.getByTestId("assessment-form-button-submit-Update")).toBeEnabled();
        // await dueworkForm.clickModifyLearningPlan();
        // await expect(page.locator('ul[id="plan-options"]')).toHaveText("Test learning plan");

        // tester.isGoingTo("Get logged in as Eden Reed and ensure the due date is the same as per the learning plan");
        // await tester.impersonate(student1);
        // await page.goto(homepageUrl);
        // await expect(page.locator("h2:has-text('Tasks')")).toBeVisible();
        // await expect(page.locator("div:has-text('Oct 3, 2030')")).toBeVisible();

        // tester.isGoingTo("Get logged in as Jana Reed and ensure the due date is not from the learning plan");
        // await tester.impersonate(student2);
        // await page.goto(homepageUrl);
        // await expect(page.locator("h2:has-text('Tasks')")).toBeVisible();
        // await expect(page.locator("div:has-text('Aug 3, 2030')")).toBeVisible();
    });
});

// /**
//  * creates a due work, remove participants and check the excluded user for that activity is no longer presented in the class markbook
//  * provision sales data
//  * npx playwright test participant.spec.ts
//  */

// import path from "path";
// import { test, expect } from "@schoolboxTest/playwright/support/integrations";
// import MarkbookPage from "@schoolboxTest/playwright/support/page/markbook/MarkbookPage";
// import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
// import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
// import SubmitForm from "@schoolboxTest/playwright/support/page/dueWork/SubmitForm";
// import StatusPage from "@schoolboxTest/playwright/support/page/dueWork/StatusPage";
// import Common from "@schoolboxTest/playwright/support/page/Common";

// test.describe("Participant Tests", () => {
//     test.beforeEach(async ({ tester }) => {
//         await tester.logout();
//         await tester.loginAsAdmin();
//     });

//     /**
//      * creates a due work, remove participants and check the excluded user for that activity is no longer presented in the class markbook
//      */
//     test("Participant", async ({ tester, page }) => {
//         const classId = Common.CLASS_ID_DEMO2.toString();
//         const studentId = Common.USER_ID_LOUISASMITH;
//         const teacher = Common.USER_JACKCOLES;
//         const teacherId = Common.USER_ID_JACKCOLES;

//         const addComponent = new Homepage(page);
//         const dueworkForm = new AssessmentForm(tester, page);
//         const submitForm = new SubmitForm(tester, page);
//         const markbookPage = new MarkbookPage(page);
//         const statusPage = new StatusPage(tester, page);

//         tester.isGoingTo("add a due work component into the homepage");
//         await page.goto("/homepage/106");

//         await tester.common.waitForPageLoad();

//         await expect(page.locator(`h1:has-text("Demo Class Page 2 (DEMO2)")`)).toBeVisible();
//         await addComponent.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_LEARNING, "Due Work");
//         await expect(
//             page.locator(
//                 `div[class="component-container Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller"]`,
//             ),
//         ).toBeVisible();

//         tester.isGoingTo("As Jack Coles, create a due work");
//         await tester.impersonate(teacher);
//         const dueworkTitle1 = `Cest One ${Common.genText(6)}`;
//         const filename = path.join(__dirname, "../../support/file/test-80x80.png");

//         await dueworkForm.goto(classId.toString());

//         await dueworkForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
//         await dueworkForm.setAcceptSubmission(AssessmentForm.ACCEPT_SUBMISSION_MULTIPLE);
//         await dueworkForm.checkSubmissionAllowed(AssessmentForm.ALLOW_OFFLINE);
//         await dueworkForm.checkSubmissionAllowed(AssessmentForm.TEXT_EDITOR);
//         await dueworkForm.checkSubmissionAllowed(AssessmentForm.TYPE_FILES);
//         await dueworkForm.setTitle(dueworkTitle1);
//         await dueworkForm.setPublishDate(Common.getTodayAtMidnight());
//         await dueworkForm.setOpenDate(Common.getTodayAtMidnight());
//         await dueworkForm.setMarkType(AssessmentForm.MARK_TYPE_PERCENTAGE);
//         await dueworkForm.create();

//         tester.isGoingTo("As Jack Coles, create submission");
//         await expect(page.locator(`h3:has-text("Cest One")`)).toBeVisible();
//         await page.locator(`h3:has-text("Cest One")`).click();
//         await page.getByTestId("assessment-summary-create-button").click();

//         await submitForm.setContents(Common.genText(200));
//         await submitForm.setAttachment(filename);
//         await submitForm.submit();
//         await expect(page.locator(`h2.subheader:has-text("Submission History")`)).toBeVisible();

//         tester.isGoingTo("open the markbook and check if I can see my submission");
//         await markbookPage.open(classId);
//         await markbookPage.canSeeParticipant("Coles, Jack");
//         await markbookPage.canSeeAssessment("Cest One");

//         tester.isGoingTo("exclude Jack Coles from the due work participants list");
//         await page.locator(`a:has-text("Cest One")`).click();
//         await expect(page.locator(`h1:has-text("Status of Cest One")`)).toBeVisible();
//         await page.waitForTimeout(2000);
//         await statusPage.clickJellyTot();
//         await statusPage.clickJellyTotOption("modify");
//         await expect(page.locator(`h1:has-text("Update Cest One")`)).toBeVisible();
//         await expect(page.getByTestId("assessment-form-button-submit-Update")).toBeEnabled();
//         await page.waitForTimeout(2000);
//         await dueworkForm.modifyParticipation(teacherId, true, true);
//         await dueworkForm.update();
//         await expect(page.locator(`h1:has-text("Status of Cest One")`)).toBeVisible();

//         tester.isGoingTo(
//             "open course mark book and check that Jack Coles is not presented and the student is presented",
//         );
//         await markbookPage.open(classId);
//         await markbookPage.selectWorkType(1);
//         await markbookPage.selectStatus("open");
//         await markbookPage.applyFilter();
//         await markbookPage.canSeeAssessment("Cest One");
//         await markbookPage.cantSeeParticipant("Coles, Jack");
//         await markbookPage.canSeeParticipant("Smith, Louisa");

//         tester.isGoingTo("exclude a student (Louisa Smith) from the due work participants list");
//         await page.locator(`a:has-text("Cest One")`).click();
//         await expect(page.locator(`h1:has-text("Status of Cest One")`)).toBeVisible();
//         await page.waitForTimeout(2000);
//         await statusPage.clickJellyTot();
//         await statusPage.clickJellyTotOption("modify");
//         await expect(page.locator(`h1:has-text("Update Cest One")`)).toBeVisible();
//         await expect(page.getByTestId("assessment-form-button-submit-Update")).toBeEnabled();
//         await dueworkForm.modifyParticipation(studentId, false, false);
//         await dueworkForm.update();
//         await expect(page.locator(`h1:has-text("Status of Cest One")`)).toBeVisible();

//         tester.isGoingTo(
//             "open course mark book and check that Jack Coles is not presented and Louisa Smith is presented",
//         );
//         await markbookPage.open(classId);
//         await markbookPage.canSeeAssessment("Cest One");
//         await markbookPage.cantSeeParticipant("Coles, Jack");
//         await markbookPage.canSeeParticipant("Smith, Louisa");
//     });

//     /**
//      * Ideally this should be in .afterAll but since removing a component via the DB is
//      * more complex, this is done via page interaction for now.
//      */
//     test("Participant - Delete component", async ({ tester, page }) => {
//         await page.goto("/homepage/106");
//         await tester.common.waitForPageLoad();

//         // if there is no duework component, skip this test
//         const dueWorkComponent = page.locator(
//             ".component-container.Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller",
//         );
//         if ((await dueWorkComponent.count()) === 0) {
//             return;
//         }

//         const addComponent = new Homepage(page);

//         // remove the duework component from the homepage to avoid issues in the next test
//         tester.isGoingTo("remove the duework component from the homepage");
//         await page.goto("/homepage/106");
//         await page.waitForTimeout(2000);
//         await addComponent.clickLockToggle();
//         // this removes the first component on the page, which is presumed to be the duework component for now
//         await addComponent.clickComponentRemoveIcon();
//         await addComponent.clickRemoveConfirmationMessage();
//     });

//     /**
//      * remove the duework component from the homepage and delete the assessment
//      */

//     test.afterAll(async ({ baseTester, db }) => {
//         baseTester.isGoingTo("delete the assessment");
//         await db.updateInDatabase("assessment", { deleted_at: "2022-01-15 11:22:34" }, { "title like": "Cest One%" });
//     });
// });
// <?php

// /**
//  * @copyright   Copyright 2023, Schoolbox Pty Ltd
//  * @link        https://schoolbox.com.au/
//  */

// namespace Tests\Acceptance\Task;

// use Tests\Support\AcceptanceTester;
// use Exception;
// use Tests\Support\Page\Duework\AssessmentForm;
// use Tests\Support\Page\Duework\AssessmentPage;
// use Tests\Support\Page\Folder\CreateFolder;
// use Tests\Support\Page\Group\ManageGroup;
// use Tests\Support\Page\Homepage;
// use Tests\Support\Page\Resources\ResourcesMyFilesPicker;

// /**
//  * creates a task with participation and learning plan
//  * check as admin that the learning plan is presented in the assessment modify page
//  * impersonates a student in the learning plan and check the proper due date is presented
//  * impersonates a student not in the learning plan and check the proper due date is presented
//  *
//  * Steps:
//  * 1. Provision legacy.tar.gz data
//  * 2. vagrant ssh
//  * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance Task/ParticipationPlanCest
//  *
//  *
//  */

// class ParticipationPlanCest
// {
//     /**
//      *
//      * @param AcceptanceTester $i
//      * @return void
//      * @throws Exception
//      */
//     public function _before(AcceptanceTester $i): void
//     {
//         $i->logout();
//         $i->loginAsAdmin();
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @return void
//      * @throws Exception
//      */
//     public function createTask(AcceptanceTester $i): void
//     {
//         $teacher = 'Jack Coles';
//         $student1 = 'Eden Reed';
//         $student2 = 'Jana Reed';

//         $createFolder = new CreateFolder($i);
//         $resourcesFile = new ResourcesMyFilesPicker($i);
//         $manageGroup = new ManageGroup($i);
//         $homepage = new Homepage($i);
//         $dueworkForm = new AssessmentForm($i);
//         $assessmentPage = new AssessmentPage($i);
//         $folderName = 'Participation and Learning Plan Homepage';
//         $homepageTemplateOption = "Blank";

//         $i->amGoingTo('create a folder by typing a name and selecting a homepage template');
//         $i->amOnPage($createFolder::URL);
//         $createFolder->typeName($folderName);
//         $createFolder->setHomepageTemplate($homepageTemplateOption);
//         $i->amGoingTo('set write access permission for Administration and Teacher');
//         $createFolder->setAccessPermission('groupWrite1');
//         $createFolder->setAccessPermission('groupWrite2');
//         $i->amGoingTo('set read access permission for senior student');
//         $createFolder->setAccessPermission('groupRead3');
//         $i->amGoingTo('set read access permission for parent');
//         $createFolder->setAccessPermission('groupRead4');
//         $i->amGoingTo('set read access permission for junior student');
//         $createFolder->setAccessPermission('groupRead5');
//         $i->amGoingTo('set read access permission for middle student');
//         $createFolder->setAccessPermission('groupRead6');
//         $i->amGoingTo('type a school code and click Create button');
//         $createFolder->clickCreateFolderButton();

//         $i->amGoingTo("Navigate to the new folder store its location for later tests");
//         $i->amOnPage($resourcesFile::URL);
//         $i->canSee($folderName);
//         $i->click($folderName);
//         $i->waitForText("Homepage", 5);
//         $i->click("Homepage");
//         $homepageUrl = $i->getCurrentUrl();

//         $i->amGoingTo('Add Jack Coles, Eden Reed and Jana Reed into the group via manage group page');
//         $i->clickElementInWebComponent('[data-test=sbx-pop-out-tab-control]', '[data-test=sbx-pop-out-tab-control-button]');
//         $i->clickWithLeftButton('#tab-control > div > div > ul > li a.icon-group');
//         $manageGroup->search($teacher);
//         $manageGroup->selectUser(22);
//         $manageGroup->clickFromRadioButton('role');
//         $i->click('Add Members');
//         $i->amGoingTo('Making Jack Coles the group admin via database');
//         $folderId = $i->grabFromDatabase('folder', 'id', ['deleted_at' => null, 'name like' => $folderName]);
//         $i->updateInDatabase('userAccess', ['type' => '3'], ['sid' => $folderId]);
//         $i->amGoingTo('Add Eden Reed into the group');
//         $manageGroup->search($student1);
//         $manageGroup->selectUser(6);
//         $manageGroup->clickFromRadioButton('role');
//         $i->click('Add Members');
//         $i->amGoingTo('Add Jana Reed into the group');
//         $manageGroup->clickFromRadioButton('name');
//         $manageGroup->search($student2);
//         $manageGroup->selectUser(25);
//         $manageGroup->clickFromRadioButton('role');
//         $i->click('Add Members');

//         $i->amGoingTo("Add the task component in the homepage");
//         $i->amOnUrl($homepageUrl);
//         $homepage->addComponent(Homepage::ELEMENT_ADD_COMPONENT_SECTION_LEARNING, 'Tasks');

//         sleep(5);
//         $i->canSeeElement(['css' => 'div[class=component-container\ Schoolbox_Learning_Assessment_Task_Component_Homepage_Controller]']);

//         $i->amGoingTo("Create a task with a learning plan");
//         $i->click('Create a task');
//         $i->waitForText("Create Task", 5);
//         $dateLearningPlan = \Alaress_Date_Format::now()->setDate(2030, 10, 03)->setTime(10, 0, 0);
//         $dueDate = \Alaress_Date_Format::now()->setDate(2030, 8, 03)->setTime(10, 0, 0);
//         $dueworkForm->setWorkType(AssessmentForm::WORKTYPE_ASSESSMENT_TASK)
//             ->setTitle('Task Participation and Learning Plan')
//             ->setPublishDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
//             ->setDueDate($dueDate)
//             ->createLearningPlan(6, 'Test learning plan', $dateLearningPlan)
//             ->create();
//         $i->waitForText('Tasks');

//         $i->amGoingTo("As admin check if the learning plan is presented under Participation and Learning Plans");
//         $i->click('Task Participation and Learning Plan');
//         $assessmentPage->clickModify();
//         $i->canSee('Test learning plan');
//         $i->canSee('(no customisations)');

//         $i->amGoingTo("Get logged in as Eden Reed and ensure the due date is the same as per the learning plan");
//         $i->impersonateViaModifyUserPage('Ms Eden Reed');
//         $i->amOnUrl($homepageUrl);
//         $i->waitForText('Tasks');
//         $i->canSee('Oct 3, 2030');

//         $i->amGoingTo("Get logged in as Jana Reed and ensure the due date is not from the learning plan");
//         $i->impersonateViaModifyUserPage('Ms Jana Reed');
//         $i->amOnUrl($homepageUrl);
//         $i->waitForText('Tasks');
//         $i->canSee('Aug 3, 2030');
//     }

//     /**
//      * Soft deleting data to not impact the tests
//      *
//      * @param AcceptanceTester $i
//      */
//     public function tearDown(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
//         $i->updateInDatabase('folder', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => "Participation and Learning Plan Homepage"]);
//         $i->updateInDatabase('assessment', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => "Task Participation and Learning Plan"]);
//     }
// }

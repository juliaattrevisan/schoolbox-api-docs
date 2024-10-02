import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import QuestionBankPage from "@schoolboxTest/playwright/support/page/survey/QuestionBankPage";
import SummaryPage from "@schoolboxTest/playwright/support/page/survey/SummaryPage";
import SectionForm from "@schoolboxTest/playwright/support/page/survey/SectionForm";
import SurveyForm from "@schoolboxTest/playwright/support/page/survey/SurveyForm";
import QuestionForm from "@schoolboxTest/playwright/support/page/survey/QuestionForm";
import Common from "@schoolboxTest/playwright/support/page/Common";

/**
 * creates and modify a survey, add a question to the question bank
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test questionBank.spec.ts
 */

test.describe("Question Bank tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    /**
     * Tests adding and modifying survey questions in question bank
     */
    test("Question Bank", async ({ tester, page, db }) => {
        const classId = Common.CLASS_ID_9AENG;
        const surveyTitle = `Cest Survey One ${Common.genText(6)}`;
        const sectionTitle = `Cest Section One ${Common.genText(6)}`;
        const question1 = `Question ${Common.genText(100)}`;
        const question1Type = QuestionForm.TYPE_SHORT_ANSWER;
        const question2 = `Question ${Common.genText(50)}`;
        const question2Type = QuestionForm.TYPE_SHORT_SIGNATURE;
        const question3 = `Question ${Common.genText(80)}`;
        const question3Type = QuestionForm.TYPE_YESNO;
        const categoryId = QuestionBankPage.CATEGORY_ELECTIVE_SELECTION;

        const surveyForm = new SurveyForm(page);
        const questionBankPage = new QuestionBankPage(page);
        const summaryPage = new SummaryPage(page);
        const sectionForm = new SectionForm(page);
        const questionForm = new QuestionForm(page, tester, db);

        tester.isGoingTo("create survey");
        await surveyForm.goto(classId);
        await surveyForm.setTitle(surveyTitle);
        await surveyForm.create();

        tester.isGoingTo("create section");
        await sectionForm.setTitle(sectionTitle);
        await sectionForm.create();
        const sectionId = await questionForm.getSectionId();

        tester.isGoingTo("create question");
        await questionForm.setType(question1Type);
        await questionForm.setQuestion(question1);
        await questionForm.create();
    });
});
// import { test, expect } from "@schoolboxTest/playwright/support/integrations";
// import Badges from "@schoolboxTest/playwright/support/page/badges/Badges";
// import CreateBadge from "@schoolboxTest/playwright/support/page/badges/CreateBadge";
// import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";

// /**
//  * This test sets a goal, award an achievement, remove the goal, withdraw the achievement, adds an evidence
//  * Steps:
//  * 1. Provision sales.tar.gz data
//  * 2. Execute npx playwright test goalsAchievements.spec.ts
//  */

// test.describe("Goals and Achievements tests", () => {
//     /**
//      * Deleting Bagdes when the test is about to start so it does not get impacted by other tests already executed
//      */
//     test.beforeAll(async ({ baseTester, db }) => {
//         baseTester.isGoingTo("update delete_at for the badge so it does not impact the news tests");
//         await db.updateInDatabase("badge", { deleted_at: "2022-01-15 11:22:34" }, { "name like": "Automation Badge%" });
//     });

//     test.beforeEach(async ({ tester }) => {
//         await tester.loginAsAdmin();
//     });

//     /**
//      * on learning/badge page create a Badge
//      * set it as a goal to Jana Reed
//      * ensure the goal is presented on Jana Reed's activity feed
//      */
//     test("Set goals", async ({ tester, page, db }) => {
//         const badges = new Badges(tester, page);
//         const createBadge = new CreateBadge(tester, page);
//         const profilePage = new ProfilePage(tester, page);

//         const badgeName = "Automation Badge created to set goal from Badges page";
//         const student = "Jana Reed";
//         const studentId = 25;
//         await badges.goto();
//         let badgeId = (await db.grabFromDatabase("badge", "id", {
//             deleted_at: null,
//             "name like": badgeName,
//         })) as number;
//         if (!badgeId) {
//             tester.isGoingTo("Create a Badge");
//             await createBadge.createBadge(tester, page, badgeName);
//             badgeId = (await db.grabFromDatabase("badge", "id", {
//                 deleted_at: null,
//                 "name like": badgeName,
//             })) as number;
//         }
//         tester.isGoingTo("Set a Goal to Jana Reed");
//         tester.isGoingTo("click the 3-dot menu and click Set Goal");
//         await badges.clickBadgeDropdown(badgeId);
//         await badges.clickDropdownOptionFirstElement(badgeId, "goal");
//         tester.isGoingTo("search and select Jana Reed on the modal");
//         await badges.selectStudentSetGoal(student);
//         tester.isGoingTo("click Set Goal button on the modal");

//         await badges.clickSetGoalorAwardAchievement("Set goal");

//         tester.isGoingTo("check the goal is presented on Jana Reed profile page activity feed");
//         await profilePage.goto(studentId.toString());
//         await expect(page.locator("#container")).toContainText(badgeName);
//         tester.isGoingTo("check the goal is presented on Jana Reed profile page tab Goals");
//         await profilePage.clickTab("goals");
//         await expect(page.locator("#container")).toContainText(badgeName);
//     });

//     /*
//      * award an achievement to Jana Reed from the Badges page
//      * ensure the achievement is presented on Jana Reed's activity feed
//      */
//     test("Award achievement", async ({ tester, page, db }) => {
//         const createBadge = new CreateBadge(tester, page);
//         const badges = new Badges(tester, page);
//         const profilePage = new ProfilePage(tester, page);
//         const badgeName = "Automation Badge created for award achievement from Badges page";
//         const student = "Jana Reed";
//         const studentId = 25;
//         await badges.goto();
//         let badgeId = (await db.grabFromDatabase("badge", "id", {
//             deleted_at: null,
//             "name like": badgeName,
//         })) as number;
//         if (!badgeId) {
//             tester.isGoingTo("Create a Badge");
//             await createBadge.createBadge(tester, page, badgeName);
//             badgeId = (await db.grabFromDatabase("badge", "id", {
//                 deleted_at: null,
//                 "name like": badgeName,
//             })) as number;
//         }
//         tester.isGoingTo("Award achievement to Jana Reed");
//         tester.isGoingTo("click the 3-dot menu and click Award Achievement");
//         await badges.clickBadgeDropdown(badgeId);
//         await badges.clickDropdownOptionFirstElement(badgeId, "achievement");
//         tester.isGoingTo("search and select Jana Reed on the modal");
//         await badges.selectStudentAwardAchievement(student);
//         tester.isGoingTo("click Award achievement button on the modal");
//         await badges.clickSetGoalorAwardAchievement("Award");
//         tester.isGoingTo("check the achievement is presented on Jana Reed activity feed");
//         await profilePage.goto(studentId.toString());
//         await expect(page.locator("#container")).toContainText(badgeName);
//     });

//     /**
//      * remove the goal and withdraw the achievement from Jana Reed
//      * ensure the goals and achievements are  no longer presented on Jana Reed's activity feed, goals and achievement tabs on profile page
//      */

//     test("Remove goal and withdraw achievement", async ({ tester, page, db }) => {
//         const badges = new Badges(tester, page);
//         const profilePage = new ProfilePage(tester, page);
//         const badgeNameGoal = "Automation Badge created to set goal from Badges page";
//         const badgeNameAchievement = "Automation Badge created for award achievement from Badges page";
//         const studentId = 25;
//         tester.isGoingTo("Navigate to Jana Reed profile page, click Goals tab and award the goal as achievement");
//         await profilePage.goto(studentId.toString());
//         await profilePage.clickTab("goals");
//         await expect(page.locator("#goal-feed")).toContainText(badgeNameGoal);
//         await profilePage.clickBadgeOrDropdownOption(badgeNameGoal);
//         await profilePage.clickButtonInModal("Award");

//         tester.isGoingTo("Click the achievements tab, ensure the achievements are presented and withdraw them");
//         await profilePage.clickTab("achievements");
//         await expect(page.locator("#achievement-feed")).toContainText(badgeNameGoal);
//         let badgeIdAchievement = (await db.grabFromDatabase("badge", "id", {
//             deleted_at: null,
//             "name like": badgeNameAchievement,
//         })) as number;
//         await profilePage.clickBadgeAchievementDropdown(badgeIdAchievement);
//         await profilePage.clickBadgeOrDropdownOption("Withdraw achievement");
//         await badges.clickConfirm();
//         badgeIdAchievement = (await db.grabFromDatabase("badge", "id", {
//             deleted_at: null,
//             "name like": badgeNameGoal,
//         })) as number;
//         await profilePage.clickBadgeAchievementDropdown(badgeIdAchievement);
//         await profilePage.clickBadgeOrDropdownOption("Withdraw achievement");
//         await badges.clickConfirm();
//         tester.isGoingTo("Click the goals tab, ensure the goal is presented and remove it");
//         await profilePage.clickTab("goals");
//         await expect(page.locator("#container")).toContainText(badgeNameGoal);
//         await expect(page.locator("#container")).not.toContainText(badgeNameAchievement);

//         const badgeIdGoal = (await db.grabFromDatabase("badge", "id", {
//             deleted_at: null,
//             "name like": badgeNameGoal,
//         })) as number;
//         await profilePage.clickBadgeDropdown(badgeIdGoal);
//         await badges.clickDropdownOptionLastElement("close");
//         await badges.clickConfirm();
//         await expect(page.locator('[class="icon-goal"]')).toBeVisible();
//         await profilePage.clickTab("activity");
//         await expect(page.locator("#activity-feed")).not.toContainText(badgeNameAchievement);
//         await expect(page.locator("#activity-feed")).not.toContainText(badgeNameGoal);
//     });

//     /**
//      * set a goal, add an evidence, view the evidence, edit it, check it is updated, award it and check the evidence is presented
//      * withdraw the achievement, remove the evidence, award it again with evidence, check the evidence, update the evidence, check the evidence is presented properly
//      *
//      */

//     test("View evidence", async ({ tester, page, db }) => {
//         const badges = new Badges(tester, page);
//         const profilePage = new ProfilePage(tester, page);
//         const badgeNameGoal = "Automation Badge created to set goal from Badges page";
//         const student = "Jana Reed";
//         const studentId = 25;
//         tester.isGoingTo("Set a Goal to Jana Reed");
//         await badges.goto();
//         const badgeId = (await db.grabFromDatabase("badge", "id", {
//             deleted_at: null,
//             "name like": badgeNameGoal,
//         })) as number;
//         tester.isGoingTo("click the 3-dot menu and click Set Goal");
//         await badges.clickBadgeDropdown(badgeId);
//         await badges.clickDropdownOptionFirstElement(badgeId, "goal");
//         tester.isGoingTo("search and select Jana Reed on the modal");
//         await badges.selectStudentSetGoal(student);
//         tester.isGoingTo("click Set Goal button on the modal");
//         await badges.clickSetGoalorAwardAchievement("Set goal");

//         tester.isGoingTo("Navigate to Jana Reed profile page goals tab and add an evidence to the goal");
//         await profilePage.goto(studentId.toString());
//         await profilePage.clickTab("goals");
//         await expect(page.locator("#goal-feed")).toContainText(badgeNameGoal);
//         await profilePage.clickBadgeOrDropdownOption(badgeNameGoal);
//         await profilePage.clickBadgeOrDropdownOption("Add evidence");
//         await tester.fillCKEditorById("reason", "Great job, Jana!");
//         await badges.clickEvidenceSubmitButton();

//         tester.isGoingTo("Check the evidence is presented by navigating to view evidence");
//         await profilePage.goto(studentId.toString());
//         await profilePage.clickTab("goals");
//         await profilePage.clickBadgeOrDropdownOption(badgeNameGoal);
//         await profilePage.clickBadgeOrDropdownOption("View evidence");
//         await expect(page.locator("#container")).toContainText("Great job, Jana!");

//         tester.isGoingTo("Get the evidence updated");
//         await page.locator('[class="icon-modify"]').last().click();
//         await tester.fillCKEditorById("reason", "Well done, Jana!");
//         await badges.clickEvidenceSubmitButton();

//         tester.isGoingTo("Ensure the evidence text is updated");
//         await expect(page.locator("#container")).toContainText("Well done, Jana!");

//         tester.isGoingTo(
//             "Award the goal as achievement and check if it is presented on the achievements tab on the profile page",
//         );
//         await badges.clickConfirm();
//         await expect(page.locator('[class="icon-achievement"]')).toBeVisible();
//         await profilePage.goto(studentId.toString());
//         await profilePage.clickTab("achievements");
//         await expect(page.locator("#achievement-feed")).toContainText(badgeNameGoal);

//         tester.isGoingTo("Check the evidence is still presented");
//         await profilePage.clickBadgeOrDropdownOption(badgeNameGoal);
//         await profilePage.clickBadgeOrDropdownOption("View evidence");
//         await expect(page.locator("#container")).toContainText("Well done, Jana!");

//         tester.isGoingTo("Navigate to the profile page and withdraw the achievement");
//         await profilePage.goto(studentId.toString());
//         await profilePage.clickTab("achievements");
//         await profilePage.clickBadgeAchievementDropdown(badgeId);
//         await profilePage.clickBadgeOrDropdownOption("Withdraw achievement");
//         await badges.clickConfirm();

//         tester.isGoingTo("click the tab Goals and ensure the evidence is still presented to the goal");
//         await profilePage.clickTab("goals");
//         await profilePage.clickBadgeOrDropdownOption(badgeNameGoal);
//         await profilePage.clickBadgeOrDropdownOption("View evidence");
//         await expect(page.locator("#container")).toContainText("Well done, Jana!");

//         tester.isGoingTo("Remove the evidence and ensure it is no longer presented");
//         await badges.clickDropdownDelete();
//         await page.locator("a#confirm-dialog-confirm-button.button.submit").click();
//         await expect(page.locator("#container")).not.toContainText("Well done, Jana!");

//         tester.isGoingTo("Award the goal as achievement with evidence and check the evidence");
//         await profilePage.goto(studentId.toString());
//         await profilePage.clickTab("goals");
//         await profilePage.clickBadgeOrDropdownOption(badgeNameGoal);
//         await profilePage.clickBadgeOrDropdownOption("Award with evidence");
//         await tester.fillCKEditorById("reason", "Awarding this as achievement with evidence.");
//         await badges.clickEvidenceSubmitButton();
//         await profilePage.goto(studentId.toString());
//         await profilePage.clickTab("achievements");
//         await profilePage.clickBadgeOrDropdownOption(badgeNameGoal);
//         await profilePage.clickBadgeOrDropdownOption("View evidence");
//         await expect(page.locator("#container")).toContainText("Awarding this as achievement with evidence");

//         tester.isGoingTo("Get the evidence updated and ensure it is presented properly");
//         await page.locator('[class="icon-modify"]').last().click();
//         await tester.fillCKEditorById("reason", "This is updated");
//         await badges.clickEvidenceSubmitButton();
//         tester.isGoingTo("Ensure the evidence text is updated");
//         await expect(page.locator("#container")).toContainText("This is updated");
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
//         await db.updateInDatabase(
//             "badge",
//             { deleted_at: "2022-01-15 11:22:34" },
//             {
//                 "name like": "Automation Badge%",
//             },
//         );
//     });
// });
// <?php

// /**
//  * @copyright   Copyright 2019, Schoolbox Pty Ltd
//  * @link        https://schoolbox.com.au/
//  */

// namespace Tests\Acceptance\Survey;

// use Tests\Support\AcceptanceTester;
// use Tests\Support\Page\Common;
// use Tests\Support\Page\Survey\SurveyForm;
// use Tests\Support\Page\Survey\QuestionForm;
// use Tests\Support\Page\Survey\SummaryPage;
// use Tests\Support\Page\Survey\QuestionBankPage;
// use Exception;

// /**
//  *
//  * creates and modify a survey, add a question to the question bank
//  *
//  * Steps:
//  * 1. Provision legacy.tar.gz data
//  * 2. vagrant ssh
//  * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance Survey/QuestionBankCest
//  *
//  */

// class QuestionBankCest
// {
//     /**
//      * Preparations
//      *
//      * @param AcceptanceTester $i
//      * @throws Exception
//      */
//     public function _before(AcceptanceTester $i): void
//     {
//         $i->loginAsAdmin();
//     }

//     /**
//      * Tests adding and modifying survey questions in question bank
//      * @param AcceptanceTester $i
//      * @throws Exception
//      */
//     public function questionBank(AcceptanceTester $i): void
//     {
//         $classId = Common::CLASS_ID_9AENG;
//         $surveyTitle = 'Cest Survey One ' . Common::gentext(6);
//         $sectionTitle = 'Cest Section One ' . Common::gentext(6);
//         $question1 = 'Question ' . Common::gentext(100);
//         $question1Type = QuestionForm::TYPE_SHORT_ANSWER;
//         $question2 = 'Question ' . Common::gentext(50);
//         $question2Type = QuestionForm::TYPE_SHORT_SIGNATURE;
//         $question3 = 'Question ' . Common::gentext(80);
//         $question3Type = QuestionForm::TYPE_YESNO;

//         $categoryId = QuestionBankPage::CATEGORY_ELECTIVE_SELECTION;

//         $i->amGoingTo('create survey');
//         $surveyForm = new SurveyForm($i);
//         $surveyForm->openCreate($classId);
//         $sectionForm = $surveyForm->setTitle($surveyTitle)
// //            ->setOpenDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
//         ->create();

//         $assessId = $sectionForm->getAssessId();

//         $i->amGoingTo('create section');
//         $questionForm = $sectionForm->setTitle($sectionTitle)
//             ->create();
//         $sectionId = $questionForm->getSectionId();

//         $i->amGoingTo('create question');
//         $questionForm->setType($question1Type)
//             ->setQuestion($question1)
//             ->create();
//         $question1Id = $questionForm->getQuestionId($question1Type, $sectionId);

//         $i->amGoingTo('back to survey summary page');
//         $summaryPage = new SummaryPage($i);
//         $summaryPage->open($assessId, $surveyTitle);

//         $i->amGoingTo('add the question to question bank');
//         $summaryPage->addToQuestionBank($question1Id, $categoryId);

//         $i->amGoingTo('find the question in question bank and click modify');
//         $questionBankPage = $summaryPage->clickAddFromQuestionBank($sectionId);
//         $questionBankPage->clickCategory($categoryId);
//         $questionFormInBank = $questionBankPage->clickModify($question1);

//         $i->amGoingTo('save without any change');
//         $questionFormInBank->modify();

//         $i->amGoingTo('back to survey summary page');
//         $summaryPage = new SummaryPage($i);
//         $summaryPage->open($assessId, $surveyTitle);

//         $i->amGoingTo('create another question');
//         $questionForm2 = $summaryPage->clickNewQuestion($sectionId);

//         $i->amGoingTo('save the question');
//         $questionForm2->setType($question2Type)
//             ->setQuestion($question2)
//             ->create();
//         $question2Id = $questionForm->getQuestionId($question2Type, $sectionId);

//         $i->amGoingTo('back to survey summary page');
//         $summaryPage = new SummaryPage($i);
//         $summaryPage->open($assessId, $surveyTitle);

//         $i->amGoingTo('add the question to question bank');
//         $summaryPage->addToQuestionBank($question2Id, $categoryId);

//         $i->amGoingTo('find the question in question bank and click modify');
//         $questionBankPage = $summaryPage->clickAddFromQuestionBank($sectionId);
//         $questionBankPage->clickCategory($categoryId);
//         $questionFormInBank2 = $questionBankPage->clickModify($question2);

//         $i->amGoingTo('save without any change');
//         $questionFormInBank2->modify();

//         $i->amGoingTo('back to survey summary page');
//         $summaryPage = new SummaryPage($i);
//         $summaryPage->open($assessId, $surveyTitle);

//         $i->amGoingTo('create one more question');
//         $questionForm3 = $summaryPage->clickNewQuestion($sectionId);

//         $i->amGoingTo('save the question');
//         $questionForm3->setType($question3Type)
//             ->setQuestion($question3)
//             ->create();
//         $question3Id = $questionForm->getQuestionId($question3Type, $sectionId);

//         $i->amGoingTo('back to survey summary page');
//         $summaryPage = new SummaryPage($i);
//         $summaryPage->open($assessId, $surveyTitle);

//         $i->amGoingTo('add the question to question bank');
//         $summaryPage->addToQuestionBank($question3Id, $categoryId);

//         $i->amGoingTo('find the question in question bank and click modify');
//         $questionBankPage = $summaryPage->clickAddFromQuestionBank($sectionId);
//         $questionBankPage->clickCategory($categoryId);
//         $questionFormInBank3 = $questionBankPage->clickModify($question3);

//         $i->amGoingTo('save without any change');
//         $questionFormInBank3->modify();
//     }

//     /**
//      * Soft deleting data to not impact the tests
//      *
//      * @param AcceptanceTester $i
//      */
//     public function tearDown(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
//         $i->updateInDatabase('assess', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => "Automation Test Pastoral Record%"]);
//         $i->updateInDatabase('assess_question_bank', ['deleted_at' => "2022-01-15 11:22:34"], ['question like' => "%Question%"]);
//         $i->updateInDatabase('assess_question', ['deleted_at' => "2022-01-15 11:22:34"], ['question like' => "%Question%"]);
//     }
// }

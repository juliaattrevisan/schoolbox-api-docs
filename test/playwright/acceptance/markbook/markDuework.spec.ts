import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import MarkbookPage from "@schoolboxTest/playwright/support/page/markbook/MarkbookPage";
import AssessmentPage from "@schoolboxTest/playwright/support/page/dueWork/AssessmentPage";
import FeedbackForm from "@schoolboxTest/playwright/support/page/dueWork/FeedbackForm";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import StatusPage from "@schoolboxTest/playwright/support/page/dueWork/StatusPage";
import Common from "@schoolboxTest/playwright/support/page/Common";

/**
 * Clicks the due work title in the class and course mark-book and checks its dropdown options
 * Clicks each one of the due work dropdown options and checks the correct action is taken
 * Actions are:
 * - View : redirects to the due work details page
 * - View Status: redirects to the due work status page
 * - Modify: redirects to the due work modify page
 * - Import Feedback: redirects to the due work status page with the modal to import feedback opened
 * - Publish Grades & Feedback: publish the drafts - it drafts the feedback via due work status page nd then publish it via mark-book pages
 */

test.describe("Test Due Work options in class and course mark-book", () => {
    test.beforeEach(async ({ tester }) => {
        const teacher = "Jack Coles";
        await tester.loginAsAdmin();
        await tester.impersonate(teacher);
    });

    // due work drop-down tests in the implementation level
    test("Due Work title dropdown options in the class mark-book work as expected", async ({ tester, page, db }) => {
        const markbookPage = new MarkbookPage(page);
        const assessmentPage = new AssessmentPage(tester, page);
        const feedbackForm = new FeedbackForm(tester, page);
        const assessmentForm = new AssessmentForm(tester, page);
        const statusPage = new StatusPage(tester, page);
        const commonPage = new Common(page);

        // assessment id for "Persuasive Article" - class page
        const assessmentId = 665;
        // 9a english class id
        const classId = 83;
        // student id for Jamal Ali
        const studentIdJamal = 60;
        // student if for Mario Izzo
        const studentIdMario = 63;
        // student external id for Jamal Ali
        const studentExternalIdJamal = "540";
        // course id for "English 9A"
        const courseId = 6;
        // assessment id for "Persuasive Article" - course page
        const assessmentIdCourse = 158;

        try {
            tester.isGoingTo("navigate to the class markbook");
            await markbookPage.gotoClassMarkbook(classId.toString());
            await commonPage.resetFiltersToDefault();
            tester.isGoingTo(
                "click the due work title, click the 'View' option and check it redirects to the due work details page",
            );
            await markbookPage.openAssessmentDropdown(assessmentId);
            await markbookPage.clickAssessmentDropdownItem(assessmentId, "view");
            expect(page.url()).toContain(`/learning/assessments/${assessmentId}`);
            tester.isGoingTo("by clicking return it redirects back to the class mark-book");
            await assessmentPage.clickReturn();
            expect(page.url()).toContain(`/learning/markbook/class/${classId}`);
            tester.isGoingTo(
                "click the due work title, click the 'View Status' option and check it redirects to the status page",
            );
            await markbookPage.openAssessmentDropdown(assessmentId);
            await markbookPage.clickAssessmentDropdownItem(assessmentId, "view-status");
            expect(page.url()).toContain(`/learning/assessments/${assessmentId}/status`);
            tester.isGoingTo("draft some feedback for a student and navigate back to the class markbook");
            await feedbackForm.setGrade("75", studentIdJamal); // setting grade for Jamal Ali
            await feedbackForm.setComment("Good job.", studentIdJamal);
            await feedbackForm.setSpecialGradeDropdown("Absent", studentIdMario); // setting Absent for Mario Izzo
            tester.isGoingTo("check the drafted feedback is presented in the course mark-book grid");
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await markbookPage.clickShowDraftsCheckbox();
            await markbookPage.validateInputMark(studentIdJamal, assessmentIdCourse, "75 %");
            await markbookPage.validateInputMark(studentIdMario, assessmentIdCourse, "Absent");
            tester.isGoingTo("check the drafted feedback is presented in the class mark-book grid");
            await markbookPage.gotoClassMarkbook(classId.toString());
            await markbookPage.clickShowDraftsCheckbox();
            await markbookPage.validateInputMark(studentIdJamal, assessmentId, "75 %");
            await markbookPage.validateInputMark(studentIdMario, assessmentId, "Absent");
            tester.isGoingTo(
                "publish the drafts via mark-book page by clicking the due work title and 'Publish Grades & Feedback'",
            );
            await markbookPage.openAssessmentDropdown(assessmentId);
            await markbookPage.clickPublishGradesAndFeedback(assessmentId);
            await markbookPage.clickPublishAllInModal();
            tester.isGoingTo("check the drafts are published");
            await page.reload(); // reload the page to ensure the published marks are still shown
            await markbookPage.validateInputMark(studentIdJamal, assessmentId, "75 %");
            await markbookPage.validateInputMark(studentIdMario, assessmentId, "Absent");
            tester.isGoingTo("check the drafts are published in the course markbook page");
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await markbookPage.validateInputMark(studentIdJamal, assessmentIdCourse, "75 %");
            await markbookPage.validateInputMark(studentIdMario, assessmentIdCourse, "Absent");
            tester.isGoingTo("go back to the class mark-book, click the due work title and click the 'Modify' option");
            await markbookPage.gotoClassMarkbook(classId.toString());
            await markbookPage.openAssessmentDropdown(assessmentId);
            await markbookPage.clickAssessmentDropdownItem(assessmentId, "modify");
            tester.isGoingTo("update the title and click update");
            const newTitle = "Persuasive Article - Updated";
            await assessmentForm.setTitle(newTitle);
            await assessmentForm.update();
            tester.isGoingTo("check the class mark-book page to confirm the title was updated");
            await markbookPage.canSeeAssessment(newTitle);
            tester.isGoingTo("click the due work title and click the 'Import Feedback' option");
            await markbookPage.openAssessmentDropdown(assessmentId);
            await markbookPage.clickAssessmentDropdownItem(assessmentId, "import-feedback");
            await commonPage.waitForModalToBeOpen();
            await statusPage.enterFeedbackImportModalText(`${studentExternalIdJamal}	80	import feedback test`);
            await statusPage.clickImportMarkAndCommentRadio();
            await statusPage.clickImportButtonInModal();
            tester.isGoingTo("check the imported feedback is presented in the due work status page");
            await page.waitForTimeout(1000); // wait for 1s to ensure the grade value is updated in the html
            await statusPage.goto(assessmentId); // so the grade value is updated in the html
            await statusPage.verifyGradeInputForStudent(studentIdJamal, "80 %");
            await statusPage.verifyCommentForStudent("Jamal", "import feedback test");
            tester.isGoingTo("go back to the class markbook and check the draft feedback is presented in the grid");
            await markbookPage.gotoClassMarkbook(classId.toString());
            await markbookPage.clickShowDraftsCheckbox();
            await markbookPage.validateInputMark(studentIdJamal, assessmentId, "80 %");
            tester.isGoingTo("check the imported feedback is presented in the course mark-book grid");
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await markbookPage.clickShowDraftsCheckbox();
            await markbookPage.validateInputMark(studentIdJamal, assessmentIdCourse, "80 %");
        } finally {
            // clean up - reset the assessment title and clear all feedback

            const boxId = (await db.grabFromDatabase("submission_return", "box_id", {
                mark: "75 %",
                deleted_at: null,
            })) as string;

            await db.updateInDatabase(
                "submission_return",
                { deleted_at: "2025-10-01 11:20:13" },
                { box_id: boxId, deleted_at: null },
            );

            await db.updateInDatabase("assessment", { title: "Persuasive Article" }, { id: assessmentId });
        }
    });

    // due work drop-down tests in the specification level
    test("Due Work title dropdown options in the course mark-book work as expected", async ({ tester, page, db }) => {
        const markbookPage = new MarkbookPage(page);
        const assessmentPage = new AssessmentPage(tester, page);
        const feedbackForm = new FeedbackForm(tester, page);
        const assessmentForm = new AssessmentForm(tester, page);
        const statusPage = new StatusPage(tester, page);
        const commonPage = new Common(page);

        // assessment id for "Persuasive Article" - class page
        const assessmentId = 665;
        // 9a english class id
        const classId = 83;
        // student id for Jamal Ali
        const studentIdJamal = 60;
        // student id for Mario Izzo
        const studentIdMario = 63;
        // student external id for Jamal Ali
        const studentExternalIdJamal = "540";
        // course id for "English 9A"
        const courseId = 6;
        // assessment id for "Persuasive Article" - course page
        const assessmentIdCourse = 158;

        try {
            tester.isGoingTo("navigate to the course markbook");
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await commonPage.resetFiltersToDefault();
            tester.isGoingTo(
                "click the due work title, click the 'View' option and check it redirects to the due work details page",
            );
            await markbookPage.openAssessmentDropdown(assessmentIdCourse);
            await markbookPage.clickAssessmentDropdownItem(assessmentIdCourse, "view");
            expect(page.url()).toContain(`/learning/assessments/${assessmentIdCourse}`);
            tester.isGoingTo("by clicking return it redirects back to the class mark-book");
            await assessmentPage.clickReturn();
            expect(page.url()).toContain(`/learning/courseMarkbook/${courseId}`);
            tester.isGoingTo(
                "click the due work title, click the 'View Status' option and check it redirects to the status page",
            );
            await markbookPage.openAssessmentDropdown(assessmentIdCourse);
            await markbookPage.clickAssessmentDropdownItem(assessmentIdCourse, "view-status");
            expect(page.url()).toContain(`/learning/assessments/${assessmentIdCourse}/status`);
            tester.isGoingTo("check that 9A and 9B classes are listed in the status page");
            await expect(page.locator(`a[href="/homepage/83"]`)).toContainText("9A English (9ENGA)");
            await expect(page.locator(`a[href="/homepage/188"]`)).toContainText("9B English (9ENGB)");
            tester.isGoingTo("draft some feedback for a student and navigate back to the class markbook");
            await feedbackForm.setGrade("75", studentIdJamal); // setting grade for Jamal Ali
            await feedbackForm.setComment("Good job.", studentIdJamal);
            await feedbackForm.setSpecialGradeDropdown("Absent", studentIdMario); // setting Absent for Mario Izzo
            tester.isGoingTo("check the drafted feedback is presented in the class mark-book grid");
            await markbookPage.gotoClassMarkbook(classId.toString());
            await markbookPage.clickShowDraftsCheckbox();
            await markbookPage.validateInputMark(studentIdJamal, assessmentId, "75 %");
            await markbookPage.validateInputMark(studentIdMario, assessmentId, "Absent");
            tester.isGoingTo("check the drafted feedback is presented in the course mark-book grid");
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await markbookPage.clickShowDraftsCheckbox();
            await markbookPage.validateInputMark(studentIdJamal, assessmentIdCourse, "75 %");
            await markbookPage.validateInputMark(studentIdMario, assessmentIdCourse, "Absent");

            tester.isGoingTo(
                "publish the drafts via mark-book page by clicking the due work title and 'Publish Grades & Feedback'",
            );
            await markbookPage.openAssessmentDropdown(assessmentIdCourse);
            await markbookPage.clickPublishGradesAndFeedback(assessmentIdCourse);
            await markbookPage.clickPublishAllInModal();
            tester.isGoingTo("check the drafts are published");
            await page.reload(); // reload the page to ensure the published marks are still shown
            await markbookPage.validateInputMark(studentIdJamal, assessmentIdCourse, "75 %");
            await markbookPage.validateInputMark(studentIdMario, assessmentIdCourse, "Absent");
            tester.isGoingTo("check the drafts are published in the class markbook page");
            await markbookPage.gotoClassMarkbook(classId.toString());
            await markbookPage.validateInputMark(studentIdJamal, assessmentId, "75 %");
            await markbookPage.validateInputMark(studentIdMario, assessmentId, "Absent");
            tester.isGoingTo("go back to the course mark-book, click the due work title and click the 'Modify' option");
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await markbookPage.openAssessmentDropdown(assessmentIdCourse);
            await markbookPage.clickAssessmentDropdownItem(assessmentIdCourse, "modify");
            tester.isGoingTo("update the title and click update");
            const newTitle = "Persuasive Article - Updated via course";
            await assessmentForm.setTitle(newTitle);
            await assessmentForm.update();
            tester.isGoingTo("check the course mark-book page to confirm the title was updated");
            await markbookPage.canSeeAssessment(newTitle);
            tester.isGoingTo("click the due work title and click the 'Import Feedback' option");
            await markbookPage.openAssessmentDropdown(assessmentIdCourse);
            await markbookPage.clickAssessmentDropdownItem(assessmentIdCourse, "import-feedback");
            await commonPage.waitForModalToBeOpen();
            await statusPage.enterFeedbackImportModalText(`${studentExternalIdJamal}	80	import feedback test`);
            await statusPage.clickImportMarkAndCommentRadio();
            await statusPage.clickImportButtonInModal();
            tester.isGoingTo("check the imported feedback is presented in the due work status page");
            await page.waitForTimeout(1000); // wait for 1s to ensure the grade value is updated in the html
            await statusPage.goto(assessmentIdCourse); // so the grade value is updated in the html
            await statusPage.verifyGradeInputForStudent(studentIdJamal, "80 %");
            await statusPage.verifyCommentForStudent("Jamal", "import feedback test");
            tester.isGoingTo("go back to the class markbook and check the draft feedback is presented in the grid");
            await markbookPage.gotoClassMarkbook(classId.toString());
            await markbookPage.clickShowDraftsCheckbox();
            await markbookPage.validateInputMark(studentIdJamal, assessmentId, "80 %");
            tester.isGoingTo("check the imported feedback is presented in the course mark-book grid");
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await markbookPage.clickShowDraftsCheckbox();
            await markbookPage.validateInputMark(studentIdJamal, assessmentIdCourse, "80 %");
        } finally {
            // clean up - reset the assessment title and clear all feedback
            const boxId = (await db.grabFromDatabase("submission_return", "box_id", {
                mark: "75 %",
                deleted_at: null,
            })) as string;
            await db.updateInDatabase(
                "submission_return",
                { deleted_at: "2025-10-01 11:20:13" },
                { box_id: boxId, deleted_at: null },
            );
            await db.updateInDatabase("assessment", { title: "Persuasive Article" }, { id: assessmentId });
        }
    });
});

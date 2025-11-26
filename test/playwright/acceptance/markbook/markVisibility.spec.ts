import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import MarkbookPage from "@schoolboxTest/playwright/support/page/markbook/MarkbookPage";
import Common from "@schoolboxTest/playwright/support/page/Common";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";

/**
 * update the mark visibility via class mark book and activity modify pages
 * check the visibility is reflected correctly across class and course mark books and activity page
 * check that proper audience can or cannot see the marks based on visibility settings
 */

test.describe("Markbook mark visibility sync between mark books and activity modify page", () => {
    let markbookPage: MarkbookPage;
    let commonPage: Common;
    let assessmentForm: AssessmentForm;

    // Mario id
    const studentMarioId = 63;
    // Jamal id
    const studentJamalId = 60;
    // assessment id for "Context Research Project" - class mark book page
    const dueWorkId = 657;
    // assessment id for "Context Research Project" - course mark book page
    const dueWorkSpecId = 62;
    // assessment id for "Romeo and Juliet Post-Unit Quiz" - class mark book page
    const quizId = 656;
    // assessment id for "Romeo and Juliet Post-Unit Quiz" - course mark book page
    const quizSpecId = 151;
    // 9a english class id
    const classId = 83;
    // course id for year 9 english
    const courseId = 6;

    test.beforeEach(async ({ tester, page }) => {
        markbookPage = new MarkbookPage(page);
        commonPage = new Common(page);
        assessmentForm = new AssessmentForm(tester, page);
        const teacher = "Jack Coles";
        await tester.loginAsAdmin();
        await tester.impersonate(teacher);
    });

    test("Mark visibility reflects across the class and course mark book and activity page: visibility updated via class mark book", async ({
        tester,
        page,
        db,
    }) => {
        tester.isGoingTo("navigate to the class markbook, update the due work mark visibility to staff only");
        // await markbookPage.gotoClassMarkbook(classId.toString());
        // await commonPage.resetFiltersToDefault();
        // await markbookPage.updateActivityMarkVisibility(dueWorkId, "0");
        // tester.isGoingTo("update the quiz mark visibility to students and staff");
        // await markbookPage.updateActivityMarkVisibility(quizId, "1");

        // tester.isGoingTo("check the due work and quiz mark visibility is reflected in the activity modify page");
        // await assessmentForm.gotoAssessmentModifyPage(dueWorkId);
        // await assessmentForm.checkVisibilityOptionSelected("Staff Only");
        // await assessmentForm.gotoAssessmentModifyPage(quizId);
        // await assessmentForm.checkVisibilityOptionSelected("Staff and Students");

        tester.isGoingTo("check the due work and quiz mark visibility is reflected in the course mark book");
        await markbookPage.gotoCourseMarkbook(courseId.toString());
        // await commonPage.resetFiltersToDefault();
        await markbookPage.checkVisibilityOptionSelected(dueWorkSpecId, "0");
        await markbookPage.checkVisibilityOptionSelected(quizSpecId, "1");
    });
});

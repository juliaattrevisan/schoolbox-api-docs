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
        await tester.impersonate(teacher);
    });

    test("Mark visibility reflects across the course mark book and activity page: visibility updated via class mark book", async ({
        tester,
        db,
    }) => {
        try {
            tester.isGoingTo(
                "navigate to the class markbook, update the due work mark visibility to staff only and the quiz mark visibility to students and staff",
            );
            await markbookPage.gotoClassMarkbook(classId.toString());
            await commonPage.resetFiltersToDefault();
            await markbookPage.updateMultipleActivityMarkVisibilities([dueWorkId, quizId], ["0", "1"]);

            tester.isGoingTo("check the due work and quiz mark visibility is reflected in the activity modify page");
            await assessmentForm.checkVisibilityOptionsSelected(
                [dueWorkId, quizId],
                ["Staff Only", "Staff and Students"],
            );

            tester.isGoingTo("check the due work and quiz mark visibility is reflected in the course mark book");
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await commonPage.selectFilterOption("14", "83"); // filtering by class 9A English
            await markbookPage.checkVisibilityOptionsSelected([dueWorkSpecId, quizSpecId], ["0", "1"]);
        } finally {
            tester.isGoingTo("clean up - reset the course markbook filters");
            await db.updateInDatabase(
                "filter_selection",
                { selected: 0 },
                { filter_id: 14, user_id: 22, page: "courseMarkbook", selected: 1 },
            );
            await db.updateInDatabase("submission_box", { mark_visibility: 2 }, { assessment_id: dueWorkId });
            await db.updateInDatabase("submission_box", { mark_visibility: 2 }, { assessment_id: dueWorkSpecId });
            await db.updateInDatabase("assess", { mark_visibility: 2 }, { assessment_id: quizId });
            await db.updateInDatabase("assess", { mark_visibility: 2 }, { assessment_id: quizSpecId });
        }
    });

    test("Mark visibility reflects across the class mark book and activity page: visibility updated via course mark book", async ({
        tester,
        db,
    }) => {
        try {
            tester.isGoingTo(
                "navigate to the course markbook, update the due work mark visibility to staff only and the quiz mark visibility to students and staff",
            );
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await commonPage.selectFilterOption("14", "83"); // filtering by class 9A English
            await markbookPage.updateMultipleActivityMarkVisibilities([dueWorkSpecId, quizSpecId], ["0", "1"]);

            tester.isGoingTo("check the due work and quiz mark visibility is reflected in the activity modify page");
            await assessmentForm.checkVisibilityOptionsSelected(
                [dueWorkId, quizId],
                ["Staff Only", "Staff and Students"],
            );

            tester.isGoingTo("check the due work and quiz mark visibility is reflected in the class mark book");
            await markbookPage.gotoClassMarkbook(classId.toString());
            await markbookPage.checkVisibilityOptionsSelected([dueWorkId, quizId], ["0", "1"]);
        } finally {
            tester.isGoingTo("clean up - reset the course markbook filters");
            await db.updateInDatabase(
                "filter_selection",
                { selected: 0 },
                { filter_id: 14, user_id: 22, page: "courseMarkbook", selected: 1 },
            );
            await db.updateInDatabase("submission_box", { mark_visibility: 2 }, { assessment_id: dueWorkId });
            await db.updateInDatabase("submission_box", { mark_visibility: 2 }, { assessment_id: dueWorkSpecId });
            await db.updateInDatabase("assess", { mark_visibility: 2 }, { assessment_id: quizId });
            await db.updateInDatabase("assess", { mark_visibility: 2 }, { assessment_id: quizSpecId });
        }
    });

    test("Mark visibility reflects across the class mark book and activity page: visibility updated via activity modify page", async ({
        tester,
        db,
        page,
    }) => {
        try {
            tester.isGoingTo(
                "navigate to the quiz(class level) and due work(course level) modify pages, update to staff and students and staff only the visibility",
            );
            await markbookPage.gotoClassMarkbook(classId.toString());
            await commonPage.resetFiltersToDefault();
            await markbookPage.openAssessmentDropdown(quizId);
            await markbookPage.clickAssessmentDropdownItem(quizId, "modify");
            await assessmentForm.setVisibility("1");
            await assessmentForm.update();
            await expect(page.getByTestId("panel-apply-filters-button")).toBeVisible(); // wait for page to load

            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await commonPage.selectFilterOption("14", "83"); // filtering by class 9A English
            await markbookPage.openAssessmentDropdown(dueWorkSpecId);
            await markbookPage.clickAssessmentDropdownItem(dueWorkSpecId, "modify");
            await assessmentForm.setVisibility("0");
            await assessmentForm.update();
            await expect(page.getByTestId("panel-apply-filters-button")).toBeVisible(); // wait for page to load

            tester.isGoingTo(
                "check the quiz mark visibility is reflected in the markbook pages and the due work is not reflected",
            );
            await markbookPage.gotoClassMarkbook(classId.toString());
            await markbookPage.checkVisibilityOptionsSelected([quizId], ["1"]);
            await markbookPage.checkVisibilityOptionsSelected([dueWorkId], ["2"]);

            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await markbookPage.checkVisibilityOptionsSelected([quizSpecId], ["1"]);
            await markbookPage.checkVisibilityOptionsSelected([dueWorkSpecId], ["2"]);

            tester.isGoingTo("check the quiz mark visibility is not reflected in the quiz course level modify page");
            await assessmentForm.gotoAssessmentModifyPage(quizSpecId.toString());
            await assessmentForm.checkVisibilityOptionsSelected([quizSpecId], ["Staff, Students and their Parents"]);

            tester.isGoingTo(
                "check the due work mark visibility is not reflected in the due work class level modify page",
            );
            await assessmentForm.gotoAssessmentModifyPage(dueWorkId.toString());
            await assessmentForm.checkVisibilityOptionsSelected([dueWorkId], ["Staff, Students and their Parents"]);
        } finally {
            tester.isGoingTo("clean up - reset the course markbook filters");
            await db.updateInDatabase(
                "filter_selection",
                { selected: 0 },
                { filter_id: 14, user_id: 22, page: "courseMarkbook", selected: 1 },
            );
            await db.updateInDatabase("submission_box", { mark_visibility: 2 }, { assessment_id: dueWorkId });
            await db.updateInDatabase("submission_box", { mark_visibility: 2 }, { assessment_id: dueWorkSpecId });
            await db.updateInDatabase("assess", { mark_visibility: 2 }, { assessment_id: quizId });
            await db.updateInDatabase("assess", { mark_visibility: 2 }, { assessment_id: quizSpecId });
        }
    });
});

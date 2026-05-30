import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import MarkbookPage from "@schoolboxTest/playwright/support/page/markbook/MarkbookPage";
import Common from "@schoolboxTest/playwright/support/page/Common";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";

/**
 * update the mark visibility via class mark book and activity modify pages
 * check the visibility is reflected correctly across class and course mark books and activity page
 * check that proper audience can or cannot see the marks based on visibility settings
 */

test.describe.skip("Markbook mark visibility sync between mark books and activity modify page", () => {
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
    // Filter in the course mark book - Folders id
    const filterFoldersIdCourseMarkbook = 14;

    test.beforeEach(async ({ tester, page }) => {
        markbookPage = new MarkbookPage(page);
        commonPage = new Common(page);
        assessmentForm = new AssessmentForm(tester, page);
        const teacher = "Jack Coles";
        await tester.impersonate(teacher);
    });

    test.afterEach(async ({ baseTester, db }) => {
        baseTester.isGoingTo("clean up - reset the course markbook filters");
        await db.updateInDatabase("submission_box", { mark_visibility: 2 }, { assessment_id: dueWorkId });
        await db.updateInDatabase("submission_box", { mark_visibility: 2 }, { assessment_id: dueWorkSpecId });
        await db.updateInDatabase("assess", { mark_visibility: 2 }, { assessment_id: quizId });
        await db.updateInDatabase("assess", { mark_visibility: 2 }, { assessment_id: quizSpecId });
        await db.updateInDatabase(
            "filter_selection",
            { selected: 0 },
            {
                filter_id: 14,
                user_id: 22,
                page: "courseMarkbook",
                selected: 1,
            },
        );
    });

    test("Mark visibility reflects across the course mark book and activity page: visibility updated via class mark book", async ({
        tester,
    }) => {
        tester.isGoingTo(
            "navigate to the class markbook, update the due work mark visibility to staff only and the quiz mark visibility to students and staff",
        );
        await markbookPage.gotoClassMarkbook(classId.toString());
        await commonPage.resetFiltersToDefault();
        await markbookPage.updateMultipleActivityMarkVisibilities(
            [dueWorkId, quizId],
            [MarkbookPage.VISIBILITY_STAFF_ONLY.toString(), MarkbookPage.VISIBILITY_STAFF_AND_STUDENT],
        );

        tester.isGoingTo("check the due work and quiz mark visibility is reflected in the activity modify page");
        await assessmentForm.assessmentModifyCheckVisibilityOptionsSelected(
            [dueWorkId, quizId],
            ["Staff Only", "Staff and Students"],
        );

        tester.isGoingTo("check the due work and quiz mark visibility is reflected in the course mark book");
        await markbookPage.gotoCourseMarkbook(courseId.toString());
        await commonPage.selectFilterOption(filterFoldersIdCourseMarkbook.toString(), classId.toString()); // filtering by class 9A English
        await markbookPage.checkVisibilityOptionsSelected(
            [dueWorkSpecId, quizSpecId],
            [MarkbookPage.VISIBILITY_STAFF_ONLY.toString(), MarkbookPage.VISIBILITY_STAFF_AND_STUDENT.toString()],
        );
    });

    test("Mark visibility reflects across the class mark book and activity page: visibility updated via course mark book", async ({
        tester,
    }) => {
        tester.isGoingTo(
            "navigate to the course markbook, update the due work mark visibility to staff only and the quiz mark visibility to students and staff",
        );
        await markbookPage.gotoCourseMarkbook(courseId.toString());
        await commonPage.selectFilterOption(filterFoldersIdCourseMarkbook.toString(), classId.toString()); // filtering by class 9A English
        await markbookPage.updateMultipleActivityMarkVisibilities(
            [dueWorkSpecId, quizSpecId],
            [MarkbookPage.VISIBILITY_STAFF_ONLY.toString(), MarkbookPage.VISIBILITY_STAFF_AND_STUDENT],
        );

        tester.isGoingTo("check the due work and quiz mark visibility is reflected in the activity modify page");
        await assessmentForm.assessmentModifyCheckVisibilityOptionsSelected(
            [dueWorkId, quizId],
            ["Staff Only", "Staff and Students"],
        );

        tester.isGoingTo("check the due work and quiz mark visibility is reflected in the class mark book");
        await markbookPage.gotoClassMarkbook(classId.toString());
        await markbookPage.checkVisibilityOptionsSelected(
            [dueWorkId, quizId],
            [MarkbookPage.VISIBILITY_STAFF_ONLY.toString(), MarkbookPage.VISIBILITY_STAFF_AND_STUDENT],
        );
    });

    test("Mark visibility reflects across the class mark book and activity page: visibility updated via activity modify page", async ({
        tester,
        page,
    }) => {
        tester.isGoingTo(
            "navigate to the quiz (class level) and due work (course level) modify pages, update the quiz visibility to 'Staff and Students' and the due work visibility to 'Staff Only'",
        );
        await markbookPage.gotoClassMarkbook(classId.toString());
        await commonPage.resetFiltersToDefault();
        await markbookPage.openAssessmentDropdown(quizId);
        await markbookPage.clickAssessmentDropdownItem(quizId, "modify");
        await assessmentForm.setVisibility(AssessmentForm.VISIBILITY_STAFF_AND_STUDENT.toString());
        await assessmentForm.update();
        await expect(page.getByTestId("panel-apply-filters-button")).toBeVisible(); // wait for page to load
        await page.waitForLoadState("networkidle"); // wait for page to load

        await markbookPage.gotoCourseMarkbook(courseId.toString());
        await commonPage.selectFilterOption(filterFoldersIdCourseMarkbook.toString(), classId.toString()); // filtering by class 9A English
        await markbookPage.openAssessmentDropdown(dueWorkSpecId);
        await markbookPage.clickAssessmentDropdownItem(dueWorkSpecId, "modify");
        await assessmentForm.setVisibility(AssessmentForm.VISIBILITY_STAFF_ONLY.toString());
        await assessmentForm.update();
        await expect(page.getByTestId("panel-apply-filters-button")).toBeVisible(); // wait for page to load
        await page.waitForLoadState("networkidle"); // wait for page to load

        tester.isGoingTo(
            "check the quiz mark visibility is reflected in the markbook pages and the due work is not reflected",
        );
        await markbookPage.gotoClassMarkbook(classId.toString());
        await markbookPage.checkVisibilityOptionsSelected(
            [quizId],
            [MarkbookPage.VISIBILITY_STAFF_AND_STUDENT.toString()],
        );
        await markbookPage.checkVisibilityOptionsSelected([dueWorkId], [MarkbookPage.VISIBILITY_ALL.toString()]);

        await markbookPage.gotoCourseMarkbook(courseId.toString());
        await markbookPage.checkVisibilityOptionsSelected(
            [quizSpecId],
            [MarkbookPage.VISIBILITY_STAFF_AND_STUDENT.toString()],
        );
        await markbookPage.checkVisibilityOptionsSelected([dueWorkSpecId], [MarkbookPage.VISIBILITY_ALL.toString()]);

        tester.isGoingTo("check the quiz mark visibility is not reflected in the quiz course level modify page");
        await assessmentForm.assessmentModifyCheckVisibilityOptionsSelected(
            [quizSpecId],
            ["Staff, Students and their Parents"],
        );

        tester.isGoingTo("check the due work mark visibility is not reflected in the due work class level modify page");
        await assessmentForm.assessmentModifyCheckVisibilityOptionsSelected(
            [dueWorkId],
            ["Staff, Students and their Parents"],
        );
    });
});

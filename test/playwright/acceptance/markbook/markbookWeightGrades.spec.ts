import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import MarkbookPage from "@schoolboxTest/playwright/support/page/markbook/MarkbookPage";
import Common from "@schoolboxTest/playwright/support/page/Common";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";

/**
 * update the activity weight via class mark book and activity modify pages
 * check the weight is reflected correctly in both places
 * check the grade calculation is correct based on the weight in the class and course mark books
 */

test.describe.skip("Markbook activity weight sync between mark books and activity modify page", () => {
    // Mario id
    const studentMarioId = 63;
    // Jamal id
    const studentJamalId = 60;
    // assessment id for "Context Research Project" - class mark book page
    const dueWorkId = 657;
    // assessment id for "Romeo and Juliet Post-Unit Quiz" - class mark book page
    const quizId = 656;
    // 9a english class id
    const classId = 83;
    // course id for year 9 english
    const courseId = 6;

    test.beforeEach(async ({ tester }) => {
        const teacher = "Jack Coles";
        await tester.loginAsAdmin();
        await tester.impersonate(teacher);
    });

    test("Activity weight reflects across the class and course mark book: weight updated via class mark book", async ({
        tester,
        page,
        db,
    }) => {
        const markbookPage = new MarkbookPage(page);
        const commonPage = new Common(page);

        try {
            tester.isGoingTo(
                "navigate to the class markbook, update the due work activity weight and check the class average and student grade",
            );
            await markbookPage.gotoClassMarkbook(classId.toString());
            await commonPage.resetFiltersToDefault();
            await markbookPage.updateActivityWeightAndVerifyGrades(
                dueWorkId,
                "100",
                [studentMarioId, studentJamalId],
                "C+",
                ["C+", "C+"],
            );

            tester.isGoingTo("check the student grade is reflected in the course mark book");
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await commonPage.resetFiltersToDefault();
            await markbookPage.verifyStudentGrades([studentMarioId, studentJamalId], ["C+", "C+"]);

            tester.isGoingTo(
                "navigate to the class markbook, update the quiz activity weight and check the class average and student grade",
            );
            await markbookPage.gotoClassMarkbook(classId.toString());
            await markbookPage.updateActivityWeightAndVerifyGrades(
                quizId,
                "0",
                [studentJamalId, studentMarioId],
                "C+",
                ["C+", "B+"],
            );

            tester.isGoingTo("check the student grade is reflected in the course mark book");
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await markbookPage.verifyStudentGrades([studentJamalId, studentMarioId], ["C+", "B+"]);

            tester.isGoingTo("check the class average gets updated");
            await markbookPage.gotoClassMarkbook(classId.toString());
            await markbookPage.updateMultipleActivityWeights([662, 659, 661], [100, 100, 100]);
            await markbookPage.verifyClassOverallAverage("B");
        } finally {
            tester.isGoingTo("reset the activity weights to their original values");
            await db.updateInDatabase("assessment", { weight: 100 }, { id: quizId });
            await db.updateInDatabase("assessment", { weight: 10 }, { id: dueWorkId });

            await db.updateInDatabase("assessment", { weight: 0 }, { id: 662 });
            await db.updateInDatabase("assessment", { weight: 50 }, { id: 659 });
            await db.updateInDatabase("assessment", { weight: 0 }, { id: 661 });
        }
    });

    test("Activity weight reflects across the class and course mark book: weight updated via activity modify page", async ({
        tester,
        page,
        db,
    }) => {
        const markbookPage = new MarkbookPage(page);
        const commonPage = new Common(page);
        const assessmentFormPage = new AssessmentForm(tester, page);

        try {
            tester.isGoingTo(
                "navigate to the due work modify page, update the due work activity weight and check the class average and student grade in the mark books",
            );
            await markbookPage.gotoClassMarkbook(classId.toString());
            await commonPage.resetFiltersToDefault();
            await markbookPage.openAssessmentDropdown(dueWorkId.toString());
            await markbookPage.clickAssessmentDropdownItem(dueWorkId.toString(), "modify");
            await assessmentFormPage.setWeight(100);
            await assessmentFormPage.update();
            await expect(page.getByTestId("panel-apply-filters-button")).toBeVisible();

            tester.isGoingTo("check the student and overall grade is reflected in the class mark book");
            await markbookPage.verifyStudentGrades([studentMarioId, studentJamalId], ["C+", "C+"]);
            await markbookPage.verifyClassOverallAverage("C+");

            tester.isGoingTo("check the student grade is reflected in the course mark book");
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await commonPage.resetFiltersToDefault();
            await markbookPage.verifyStudentGrades([studentMarioId, studentJamalId], ["C+", "C+"]);

            tester.isGoingTo(
                "navigate to the quiz modify page, update the quiz activity weight and check the class average and student grade in the mark books",
            );
            await markbookPage.gotoClassMarkbook(classId.toString());
            await markbookPage.openAssessmentDropdown(quizId.toString());
            await markbookPage.clickAssessmentDropdownItem(quizId.toString(), "modify");
            await assessmentFormPage.setWeight(0);
            await assessmentFormPage.update();
            await expect(page.getByTestId("panel-apply-filters-button")).toBeVisible();

            tester.isGoingTo("check the student grade is reflected in the class mark book");
            await markbookPage.verifyStudentGrades([studentJamalId, studentMarioId], ["C+", "B+"]);
            await markbookPage.verifyClassOverallAverage("C+");

            tester.isGoingTo("check the student grade is reflected in the course mark book");
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await markbookPage.verifyStudentGrades([studentJamalId, studentMarioId], ["C+", "B+"]);
        } finally {
            tester.isGoingTo("reset the activity weights to their original values");
            await db.updateInDatabase("assessment", { weight: 100 }, { id: quizId });
            await db.updateInDatabase("assessment", { weight: 10 }, { id: dueWorkId });
        }
    });
});

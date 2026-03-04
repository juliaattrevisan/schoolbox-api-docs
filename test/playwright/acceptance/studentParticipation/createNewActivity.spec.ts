/**
 * Test the core functionality of student participation exclusion
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test createNewActivity.spec.ts
 */

/**
 * create a task under a due work activity for a student
 * check it cannot be saved without a title or work type
 * check it cannot be saved with the same title as the original activity
 * check it can be saved with a valid title and work type
 * check the custom activity is shown in the participation page, markbooks and course outline
 * assign the custom activity to another student
 * check the activity is shown for that student in the participation page, markbooks and course outline
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import StudentParticipation from "@schoolboxTest/playwright/support/page/studentParticipation/StudentParticipation";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import CourseOutline from "@schoolboxTest/playwright/support/page/homepage/CourseOutline";
import MarkbookPage from "@schoolboxTest/playwright/support/page/markbook/MarkbookPage";

test.describe("Student Participation Tests: new activity, title validation, checks it in the participation page, markbooks and course outline", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });

    let studentParticipation: StudentParticipation;
    let assessmentForm: AssessmentForm;
    let homepage: Homepage;
    let courseOutline: CourseOutline;
    let markbookPage: MarkbookPage;

    const classActivityId = "665";
    const studentMarioId = "63"; // Izzo, Mario
    const studentNameMario = "Izzo, Mario";
    const studentJamalId = "60"; // Ali, Jamal
    const studentNameJamal = "Ali, Jamal";
    const originalActivityTitle = "Persuasive Article";
    const customActivityTitle = "New Task";
    const classId = 83;
    const courseId = 6;

    test.beforeEach(async ({ tester, page }) => {
        studentParticipation = new StudentParticipation(tester, page);
        assessmentForm = new AssessmentForm(tester, page);
        homepage = new Homepage(tester, page);
        courseOutline = new CourseOutline(tester, page);
        markbookPage = new MarkbookPage(page);

        await tester.loginAsAdmin();
    });

    test("create a task for a due work activity", async ({ tester, page, db }) => {
        tester.isGoingTo("Navigate to a due work participation page and click to create new activity");
        await studentParticipation.goto(classActivityId);
        await studentParticipation.clickStudentDotMenu(studentMarioId);
        await studentParticipation.clickStudentActionButton("Create New Activity", studentMarioId);

        tester.isGoingTo("Choose task and attempt to save with empty title and with the same title as the due work");
        await studentParticipation.chooseActivityType("task");
        await assessmentForm.clickCreate();
        await expect(page.locator("#title-error")).toHaveText("This field is required.");
        await expect(page.locator("#workTypeSelector-error")).toHaveText("This field is required.");
        await assessmentForm.setTitle(originalActivityTitle);
        await assessmentForm.setWorkType(5); // select "Essay" work type
        await assessmentForm.clickCreate();
        await expect(page.locator(`div[class="alert alert-box icon-alert"]`)).toHaveText(
            " Could not save activity. The activity can not have the same title as the original activity.",
        );

        tester.isGoingTo("Fill in a valid title and save");
        await assessmentForm.setTitle(customActivityTitle);
        await assessmentForm.clickCreate();

        tester.isGoingTo("Check the custom activity is shown in the participation page, course outline and markbooks");

        await studentParticipation.checkCustomActivityInTable(studentNameMario, customActivityTitle);

        await homepage.goto(classId);
        await courseOutline.clickUnitExpandCollapse("Persuasion and the Media");
        await courseOutline.clickActivityExpandCollapse("Persuasion and the Media", originalActivityTitle);
        await courseOutline.checkCustomActivityPresented(
            "Persuasion and the Media",
            originalActivityTitle,
            customActivityTitle,
        );
        await courseOutline.checkActivityMetaText([
            { activityName: originalActivityTitle, expectMetaText: "0/5 students completed" },
            { activityName: customActivityTitle, expectMetaText: "0/1 students completed" },
        ]);

        await markbookPage.gotoClassMarkbook(classId.toString());
        await markbookPage.canSeeAssessment(customActivityTitle);
        const customActivityId = (await db.grabFromDatabase("assessment", "id", {
            title: customActivityTitle,
            deleted_at: null,
        })) as number;
        // check the progress tick is shown for the student with the custom activity and not shown for another student without the custom activity
        await markbookPage.checkProgressTickVisibility(studentMarioId, customActivityId, true);
        await markbookPage.checkProgressTickVisibility(studentJamalId, customActivityId, false);

        await markbookPage.gotoCourseMarkbook(courseId.toString());
        await markbookPage.canSeeAssessment(customActivityTitle);
        // check the progress tick is shown for the student with the custom activity and not shown for another student without the custom activity
        await markbookPage.checkProgressTickVisibility(studentMarioId, customActivityId, true);
        await markbookPage.checkProgressTickVisibility(studentJamalId, customActivityId, false);
    });

    test("assign a custom activity to a student", async ({ tester, db }) => {
        tester.isGoingTo("Navigate to the participation page with the custom activity and assign it to a student");
        await studentParticipation.goto(classActivityId);
        await studentParticipation.clickStudentDotMenu(studentJamalId);
        await studentParticipation.clickStudentActionButton("Include in Existing customisation", studentJamalId);
        await studentParticipation.selectCustomisationInModal(1);
        await studentParticipation.checkCustomActivityInTable(studentNameJamal, customActivityTitle);

        tester.isGoingTo("Check the custom activity is shown in the participation page, course outline and markbooks");

        await studentParticipation.checkCustomActivityInTable(studentNameJamal, customActivityTitle);

        await homepage.goto(classId);
        await courseOutline.clickUnitExpandCollapse("Persuasion and the Media");
        await courseOutline.clickActivityExpandCollapse("Persuasion and the Media", originalActivityTitle);
        await courseOutline.checkCustomActivityPresented(
            "Persuasion and the Media",
            originalActivityTitle,
            customActivityTitle,
        );
        await courseOutline.checkActivityMetaText([
            { activityName: originalActivityTitle, expectMetaText: "0/4 students completed" },
            { activityName: customActivityTitle, expectMetaText: "0/2 students completed" },
        ]);

        await markbookPage.gotoClassMarkbook(classId.toString());
        await markbookPage.canSeeAssessment(customActivityTitle);
        const customActivityId = (await db.grabFromDatabase("assessment", "id", {
            title: customActivityTitle,
            deleted_at: null,
        })) as number;
        // check the progress tick is shown for the student with the custom activity and not shown for another student without the custom activity
        await markbookPage.checkProgressTickVisibility(studentMarioId, customActivityId, true);
        await markbookPage.checkProgressTickVisibility(studentJamalId, customActivityId, true);

        await markbookPage.gotoCourseMarkbook(courseId.toString());
        await markbookPage.canSeeAssessment(customActivityTitle);
        // check the progress tick is shown for the student with the custom activity and not shown for another student without the custom activity
        await markbookPage.checkProgressTickVisibility(studentMarioId, customActivityId, true);
        await markbookPage.checkProgressTickVisibility(studentJamalId, customActivityId, true);
    });

    test("remove a custom activity for a student", async ({ tester, db }) => {
        tester.isGoingTo("Navigate to the participation page with the custom activity and remove it for a student");
        await studentParticipation.goto(classActivityId);
        await studentParticipation.clickStudentDotMenu(studentJamalId);
        await studentParticipation.clickStudentActionButton("Remove Customisation", studentJamalId);

        tester.isGoingTo(
            "Check the custom activity is removed from the participation page, course outline and markbooks for that student",
        );
        // checking the custom activity is not presented for Jamal but still presented for Mario in the participation page
        await studentParticipation.checkCustomActivityInTable(studentNameJamal, customActivityTitle, false);
        await studentParticipation.checkCustomActivityInTable(studentNameMario, customActivityTitle);

        await homepage.goto(classId);
        await courseOutline.clickUnitExpandCollapse("Persuasion and the Media");
        await courseOutline.clickActivityExpandCollapse("Persuasion and the Media", originalActivityTitle);
        // check the custom activity is still shown in the course outline but the meta text is updated
        await courseOutline.checkCustomActivityPresented(
            "Persuasion and the Media",
            originalActivityTitle,
            customActivityTitle,
        );
        await courseOutline.checkActivityMetaText([
            { activityName: originalActivityTitle, expectMetaText: "0/5 students completed" },
            { activityName: customActivityTitle, expectMetaText: "0/1 students completed" },
        ]);

        await markbookPage.gotoClassMarkbook(classId.toString());
        // check the custom activity is still shown in the markbook but the progress tick is only shown for the student with the custom activity
        await markbookPage.canSeeAssessment(customActivityTitle);
        const customActivityId = (await db.grabFromDatabase("assessment", "id", {
            title: customActivityTitle,
            deleted_at: null,
        })) as number;
        await markbookPage.checkProgressTickVisibility(studentMarioId, customActivityId, true);
        await markbookPage.checkProgressTickVisibility(studentJamalId, customActivityId, false);

        await markbookPage.gotoCourseMarkbook(courseId.toString());
        // check the custom activity is still shown in the course markbook but the progress tick is only shown for the student with the custom activity
        await markbookPage.canSeeAssessment(customActivityTitle);
        await markbookPage.checkProgressTickVisibility(studentMarioId, customActivityId, true);
        await markbookPage.checkProgressTickVisibility(studentJamalId, customActivityId, false);
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(`Clean up database entries for participant plan: ${customActivityTitle}`);
        const customActivityId = (await db.grabFromDatabase("assessment", "id", {
            title: customActivityTitle,
            deleted_at: null,
        })) as number;

        const customPlanId = (await db.grabFromDatabase("participant_plan", "id", {
            assessment_id: customActivityId,
        })) as number;

        const originalPlanId = (await db.grabFromDatabase("participant_plan", "id", {
            assessment_id: classActivityId,
        })) as number;

        await db.dontHaveInDatabase("participant", { participant_plan_id: customPlanId });
        await db.dontHaveInDatabase("participant", { participant_plan_id: originalPlanId });

        await db.updateInDatabase(
            "assessment",
            { deleted_at: "2026-03-04 10:24:59" },
            { title: customActivityTitle, deleted_at: null },
        );
    });
});

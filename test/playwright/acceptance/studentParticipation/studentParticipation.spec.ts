/**
 * Test the core functionality of student participation exclusion
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test studentParticipation.spec.ts
 */

import { test } from "@schoolboxTest/playwright/support/integrations";
import StudentParticipation from "@schoolboxTest/playwright/support/page/studentParticipation/StudentParticipation";
import Common from "@schoolboxTest/playwright/support/page/Common";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";
import dayjs from "@js/dayjs/dayjsWithPlugins";

test.describe("Student Participation Tests: exclude, include, modify and remove customisation", () => {
    let studentParticipation: StudentParticipation;

    const userIdMario = "63";
    const studentNameMario = "Izzo, Mario";
    const description = "test";
    const dueDate = Common.getTodayAtMidnight();
    const modifyTitle = "Single task";
    const classActivityId = "650";

    /**
     * Clean up database entries for participant plan, removing the foreign key then the plan itself
     * @param db - The database helper instance
     * @param planTitle - The title of the plan to clean up
     */
    async function cleanupParticipantPlan(db: DatabaseHelper, planTitle: string): Promise<void> {
        const planId = (await db.grabFromDatabase("participant_plan", "id", {
            "name like": planTitle,
        })) as number;
        await db.dontHaveInDatabase("participant", { participant_plan_id: planId });
        await db.dontHaveInDatabase("participant_plan", { name: planTitle });
    }

    test.beforeEach(async ({ tester, page }) => {
        studentParticipation = new StudentParticipation(tester, page);
        await tester.loginAsAdmin();
    });

    test("exclude/include a student", async () => {
        const userId = "60";
        const studentName = "Ali, Jamal";
        const courseActivityId = "54";

        // Navigate to participation page and exclude student
        await studentParticipation.goto(classActivityId);
        await studentParticipation.excludeStudent(userId, studentName, true);

        // Verify exclusion in markbooks
        await studentParticipation.verifyClassMarkbookExcluded(userId, classActivityId);
        await studentParticipation.verifyCourseMarkbookExcluded(userId, courseActivityId);

        // Return to participation page and remove customisation
        await studentParticipation.goto(classActivityId);
        await studentParticipation.removeCustomisation(userId, studentName, "Excluded");

        // Verify removal in markbooks
        await studentParticipation.verifyClassMarkbookNotExcluded(userId, classActivityId);
        await studentParticipation.verifyCourseMarkbookNotExcluded(userId, courseActivityId);
    });

    test("modify current activity for student", async ({ tester, db }) => {
        const dueDateText = dayjs(dueDate).format("MMM D, YYYY h:mma");

        // Navigate to participation page and modify student's activity
        await studentParticipation.goto(classActivityId);
        await studentParticipation.modifyStudentActivity(
            userIdMario,
            studentNameMario,
            modifyTitle,
            description,
            dueDate,
        );

        // Impersonate Mario Izzo and verify the new due date of the activity
        await tester.impersonateViaUserProfilePage("Mario Izzo");
        await studentParticipation.verifyDueDateText(userIdMario, classActivityId, "today");

        // Impersonate Jack Coles and verify the new due date activity on status page
        await tester.impersonateTeacher();
        await studentParticipation.verifyDueDateSpan(classActivityId, dueDateText);

        // Clean up database entries
        await cleanupParticipantPlan(db, modifyTitle);
    });

    test("include in existing customisation", async ({ db }) => {
        // Navigate to participation page and modify student's activity
        await studentParticipation.goto(classActivityId);
        await studentParticipation.modifyStudentActivity(
            userIdMario,
            studentNameMario,
            modifyTitle,
            description,
            dueDate,
        );

        // Click the 3-dot menu for Penelope Li
        await studentParticipation.clickStudentDotMenu("29");

        // Find the Include in Existing customisation link within participant-29 element (Penelope Li)
        await studentParticipation.clickStudentActionButton("Include in Existing customisation", "29");

        // Selects the customisation on the modal and confirms the selection
        await studentParticipation.selectCustomisationInModal(1);
        await studentParticipation.confirmIncludeCustomisation();

        // Once the modal is closed, look for the table row that contains the student 'Li, Penelope'
        await studentParticipation.checkCustomActivityInTable("Li, Penelope", modifyTitle);

        // Clean up database entries
        await cleanupParticipantPlan(db, modifyTitle);
    });

    test("remove existing customisation", async ({ db }) => {
        // Navigate to participation page and modify student's activity
        await studentParticipation.goto(classActivityId);
        await studentParticipation.modifyStudentActivity(
            userIdMario,
            studentNameMario,
            modifyTitle,
            description,
            dueDate,
        );

        // Verify the new customisation is shown in the table
        await studentParticipation.checkCustomActivityInTable(studentNameMario, modifyTitle);
        await studentParticipation.removeCustomisation(userIdMario, studentNameMario, modifyTitle);
        await studentParticipation.checkCustomActivityInTable(studentNameMario, modifyTitle, false);

        // Clean up database entries
        await cleanupParticipantPlan(db, modifyTitle);
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(`Clean up database entries for participant plan: ${modifyTitle}`);
        await cleanupParticipantPlan(db, modifyTitle);
    });
});

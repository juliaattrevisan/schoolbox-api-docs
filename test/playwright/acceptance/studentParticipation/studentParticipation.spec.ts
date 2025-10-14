/**
 * Test the core functionality of student participation exclusion
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test studentParticipation.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import StudentParticipation from "@schoolboxTest/playwright/support/page/studentParticipation/StudentParticipation";
import Common from "@schoolboxTest/playwright/support/page/Common";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";
import dayjs from "@js/dayjs/dayjsWithPlugins";

test.describe("Student Participation Tests", () => {
    const modifyTitle = "Single task";

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

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("exclude/include a student", async ({ tester, page }) => {
        const studentParticipation = new StudentParticipation(tester, page);
        const userId = "60";
        const studentName = "Ali, Jamal";
        const classActivityId = "650";
        const courseActivityId = "54";

        // Navigate to participation page and exclude student
        await studentParticipation.goto();
        await studentParticipation.excludeStudent(userId, studentName, true);

        // Verify exclusion in markbooks
        await studentParticipation.verifyClassMarkbookExcluded(userId, classActivityId);
        await studentParticipation.verifyCourseMarkbookExcluded(userId, courseActivityId);

        // Return to participation page and remove customisation
        await studentParticipation.goto();
        await studentParticipation.removeCustomisation(userId, studentName);

        // Verify removal in markbooks
        await studentParticipation.verifyClassMarkbookNotExcluded(userId, classActivityId);
        await studentParticipation.verifyCourseMarkbookNotExcluded(userId, courseActivityId);
    });

    test("modify current activity for student", async ({ tester, page, db }) => {
        const studentParticipation = new StudentParticipation(tester, page);
        const userId = "63";
        const studentName = "Izzo, Mario";
        const description = "test";
        const dueDate = Common.getTodayAtMidnight();
        const dueDateText = dayjs(dueDate).format("MMM D, YYYY h:mma");

        // Navigate to participation page and modify student's activity
        await studentParticipation.goto();
        await studentParticipation.modifyStudentActivity(userId, studentName, modifyTitle, description, dueDate);

        // Impersonate Mario Izzo and verify the new due date of the activity
        await tester.impersonateViaUserProfilePage("Mario Izzo");
        await studentParticipation.verifyDueDateText(userId, "650", "Due today");

        // Impersonate Jack Coles and verify the new due date activity on status page
        await tester.impersonateTeacher();
        await studentParticipation.verifyDueDateSpan("650", dueDateText);

        // Clean up database entries
        await cleanupParticipantPlan(db, modifyTitle);
    });

    test("include in existing customisation", async ({ tester, page, db }) => {
        const studentParticipation = new StudentParticipation(tester, page);
        const userId = "63";
        const studentName = "Izzo, Mario";
        const description = "test";
        const dueDate = Common.getTodayAtMidnight();

        // Navigate to participation page and modify student's activity
        await studentParticipation.goto();
        await studentParticipation.modifyStudentActivity(userId, studentName, modifyTitle, description, dueDate);

        // Select the button with data-test attribute
        await page
            .getByTestId(StudentParticipation.DATA_TEST_POP_OUT_PARTICIPANT_BUTTON.replace("{userId}", "29"))
            .click();

        // Find the Include in Existing customisation link within participant-29 element
        await page.locator("#participant-29").getByText("Include in Existing customisation").click();

        // When the Customisation select with the id activitySelect
        const customisationSelect = page.locator("select#activitySelect");

        // Select the only option available that is not the placeholder
        await customisationSelect.selectOption({ index: 1 });

        // In the modal select the button that includes the text 'Confirm'
        await page.getByRole("button", { name: /Confirm/ }).click();

        // In the please confirm modal select the button that includes the text 'Yes'
        await page.getByRole("button", { name: /Yes/ }).click();

        // Once the modal is closed, look for the table row that contains the student 'Li, Penelope'
        const penelopeRow = page.locator('tr:has-text("Li, Penelope")');

        // For that student, they should have the newTitle text in their row
        await expect(penelopeRow).toContainText(modifyTitle);

        // Clean up database entries
        await cleanupParticipantPlan(db, modifyTitle);
    });

    test("remove existing customisation", async ({ tester, page, db }) => {
        const studentParticipation = new StudentParticipation(tester, page);
        const userId = "63";
        const studentName = "Izzo, Mario";
        const description = "test";
        const dueDate = Common.getTodayAtMidnight();

        // Navigate to participation page and modify student's activity
        await studentParticipation.goto();
        await studentParticipation.modifyStudentActivity(userId, studentName, modifyTitle, description, dueDate);

        // Verify the new customisation is shown in the table
        const studentRow = page.locator(`tr:has-text("${studentName}")`);
        await expect(studentRow).toContainText(modifyTitle);

        await studentParticipation.removeCustomisation(userId, studentName);

        // Clean up database entries
        await cleanupParticipantPlan(db, modifyTitle);
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(`Clean up database entries for participant plan: ${modifyTitle}`);
        await cleanupParticipantPlan(db, modifyTitle);
    });
});

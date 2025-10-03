/**
 * Test the core functionality of student participation exclusion
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test studentParticipation.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import StudentParticipation from "@schoolboxTest/playwright/support/page/studentParticipation/StudentParticipation";
import dayjs from "@js/dayjs/dayjsWithPlugins";

test.describe("Student Participation Tests", () => {
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
        await studentParticipation.excludeStudent(userId, studentName);

        // Verify exclusion in markbooks
        await studentParticipation.verifyClassMarkbookExcluded(userId, classActivityId);
        await studentParticipation.verifyCourseMarkbookExcluded(userId, courseActivityId);

        // Return to participation page and remove customisation
        await studentParticipation.returnToParticipationPage();
        await studentParticipation.removeCustomisation(userId, studentName);

        // Verify removal in markbooks
        await studentParticipation.verifyClassMarkbookNotExcluded(userId, classActivityId);
        await studentParticipation.verifyCourseMarkbookNotExcluded(userId, courseActivityId);
    });

    test("modify current activity for student", async ({ tester, page, db }) => {
        const studentParticipation = new StudentParticipation(tester, page);
        const userId = "63";
        const studentName = "Izzo, Mario";
        const newTitle = "Single task";
        const description = "test";
        const dueDate = dayjs().add(7, "day").format("DD/MM/YYYY HH:mma");

        // Navigate to participation page and modify student's activity
        await studentParticipation.goto();
        await studentParticipation.modifyStudentActivity(userId, studentName, newTitle, description, dueDate);

        // Impersonate Mario Izzo and verify the new due date of the activity
        await tester.impersonateViaUserProfilePage("Mario Izzo");
        await studentParticipation.verifyDueDateText(userId, "650", "6 days remaining");

        // Impersonate Jack Coles and verify the new due date activity on status page
        await tester.impersonateTeacher();
        await studentParticipation.verifyDueDateSpan("650", dueDate);

        // Clean up database entries, removing the foreign key then the plan itself
        const planId = (await db.grabFromDatabase("participant_plan", "id", {
            "name like": newTitle,
        })) as number;
        await db.dontHaveInDatabase("participant", { participant_plan_id: planId });
        await db.dontHaveInDatabase("participant_plan", { name: newTitle });
    });

    test("include in existing customisation", async ({ tester, page, db }) => {
        const studentParticipation = new StudentParticipation(tester, page);
        const userId = "63";
        const studentName = "Izzo, Mario";
        const newTitle = "Shared task";
        const description = "test";
        const dueDate = dayjs().add(7, "day").format("DD/MM/YYYY HH:mma");

        // Navigate to participation page and modify student's activity
        await studentParticipation.goto();
        await studentParticipation.modifyStudentActivity(userId, studentName, newTitle, description, dueDate);

        // Select the button with data-test attribute
        await page.click('[data-test="sbx-pop-out-participant-29-button"]');

        // Find the Include in Existing customisation link within participant-29 element
        await page.locator("#participant-29").getByText("Include in Existing customisation").click();

        // When the customisation modal opens, find the select with label Customisation
        const customisationSelect = page.locator('label:has-text("Customisation")').locator("..").locator("select");

        // Select the option which contains the newTitle text
        const optionWithTitle = customisationSelect.locator(`option:has-text("${newTitle}")`);
        await optionWithTitle.click();

        // In the modal select the button that includes the text 'Confirm'
        await page.getByRole("button", { name: /Confirm/ }).click();

        // Once the modal is closed, look for the table row that contains the student 'Li, Penelope'
        const penelopeRow = page.locator('tr:has-text("Li, Penelope")');

        // For that student, in the 'Customisation' column, they should have the newTitle text
        await expect(penelopeRow.locator("td").nth(4)).toContainText(newTitle); // Assuming Customisation is the 5th column (index 4)

        // Clean up database entries, removing the foreign key then the plan itself
        const planId = (await db.grabFromDatabase("participant_plan", "id", {
            "name like": newTitle,
        })) as number;
        await db.dontHaveInDatabase("participant", { participant_plan_id: planId });
        await db.dontHaveInDatabase("participant_plan", { name: newTitle });
    });
});

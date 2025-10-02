/**
 * Test the core functionality of student participation exclusion
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test studentParticipation.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import StudentParticipation from "@schoolboxTest/playwright/support/page/studentParticipation/StudentParticipation";

test.describe("Student Participation Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("exclude/include a student should reflect on the page as well as class and course mark books", async ({
        tester,
        page,
    }) => {
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

    test("modify current activity for a student should update the activity title", async ({ tester, page }) => {
        const studentParticipation = new StudentParticipation(tester, page);
        const userId = "63";
        const studentName = "Izzo, Mario";
        const newTitle = "Party task";

        // Navigate to participation page and modify student's activity
        await studentParticipation.goto();
        await studentParticipation.modifyStudentActivity(userId, studentName, newTitle);
    });
});

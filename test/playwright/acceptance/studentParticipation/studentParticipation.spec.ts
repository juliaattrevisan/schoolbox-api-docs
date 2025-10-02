/**
 * Test the core functionality of student participation exclusion
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test studentParticipation.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";

test.describe("Student Participation Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("should exclude a student from assessment participation", async ({ tester, page }) => {
        tester.isGoingTo("Navigate to student participation page");
        await page.goto("/learning/assessments/650/participation");
        await expect(page).toHaveTitle(/Schoolbox \[vagrant\]/);

        tester.isGoingTo("Click the Show Actions button for participant 60 (Ali, Jamal)");
        await page.locator('[data-test="sbx-pop-out-participant-60-button"]').click();

        tester.isGoingTo("Click the Exclude button in the popup");
        await page.getByRole("button", { name: "Exclude" }).click();

        tester.isGoingTo("Confirm exclusion by clicking Yes in the modal");
        await page.getByRole("button", { name: "Yes" }).click();

        tester.isGoingTo("Verify the student row shows 'Excluded' in the Customisation column");
        const aliRow = page.locator("tr").filter({ hasText: "Ali, Jamal" });
        await expect(aliRow.locator("td").nth(3)).toContainText("Excluded");

        tester.isGoingTo("Verify success message is displayed");
        await expect(page.getByText("Student excluded successfully")).toBeVisible();
    });
});

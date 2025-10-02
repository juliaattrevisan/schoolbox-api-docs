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

    test("exclude/include a student should reflect on the page as well as class and course mark books", async ({
        tester,
        page,
    }) => {
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

        tester.isGoingTo("Navigate to class markbook and verify excluded class is present");
        await page.goto("/learning/markbook/class/83?1=&5=&8=&10=&11=&12=");
        await expect(page.locator('[data-test="markbook-cell-650-60"]')).toHaveClass(/excluded/);

        tester.isGoingTo("Navigate to course markbook and verify excluded class is present");
        await page.goto("/learning/courseMarkbook/6?1=&5=&8[15]=1&10=&11=&12=&14[83]=1");
        await expect(page.locator('[data-test="markbook-cell-54-60"]')).toHaveClass(/excluded/);

        tester.isGoingTo("Navigate back to participation page to remove the exclusion");
        await page.goto("/learning/assessments/650/participation");

        tester.isGoingTo("Click the Show Actions button for participant 60 (Ali, Jamal) again");
        await page.locator('[data-test="sbx-pop-out-participant-60-button"]').click();

        tester.isGoingTo("Click Remove Customisation button");
        await page.getByRole("button", { name: " Remove Customisation" }).click();

        tester.isGoingTo("Verify success message appears for removal");
        await expect(page.getByText("Customisation removed successfully")).toBeVisible();

        tester.isGoingTo("Verify student's customisation column is now empty (no longer shows Excluded)");
        const aliRowAfterRemoval = page.locator("tr").filter({ hasText: "Ali, Jamal" });
        await expect(aliRowAfterRemoval.locator("td").nth(3)).toBeEmpty();

        tester.isGoingTo("Navigate to class markbook and verify cells no longer have excluded class");
        await page.goto("/learning/markbook/class/83?1=&5=&8=&10=&11=&12=");
        await expect(page.locator('[data-test="markbook-cell-650-60"]')).not.toHaveClass(/excluded/);

        tester.isGoingTo("Navigate to course markbook and verify cells no longer have excluded class");
        await page.goto("/learning/courseMarkbook/6?1=&5=&8[15]=1&10=&11=&12=&14[83]=1");
        await expect(page.locator('[data-test="markbook-cell-54-60"]')).not.toHaveClass(/excluded/);
    });
});

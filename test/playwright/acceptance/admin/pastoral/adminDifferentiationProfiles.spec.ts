/**
 * Tests for the differentiation profiles admin settings functionality
 *
 * @summary Tests the differentiation profiles toggle functionality and persistence
 *
 * Prerequisites:
 * 1. Provision sales.tar.gz data
 *
 * Execute: npx playwright test adminDifferentiationProfiles.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";

test.describe("Admin Differentiation Profiles", () => {
    test("should toggle differentiation profiles, show terminology, save and persist settings", async ({
        tester,
        page,
        db,
    }) => {
        try {
            tester.isGoingTo("Login as admin");
            await tester.loginAsAdmin();
            tester.isGoingTo("Visit the differentiation profiles page");
            await page.goto("/adminv2/pastoral/differentiation-profiles");
            tester.isGoingTo(
                "Toggle the checkbox input with the data-test attribute 'differentiation-profiles-toggle'",
            );
            await page.getByTestId("differentiation-profiles-toggle").click();
            tester.isGoingTo("Validate that the text 'Terminology' is now visible on the page");
            await expect(page.getByTestId("differentiation-profiles-header-Terminology")).toBeVisible();
            tester.isGoingTo("Find the Save button and click it");
            await page.getByRole("button", { name: "Save" }).click();
            tester.isGoingTo("Wait for the success message to appear");
            await expect(page.locator("text=Differentiation profiles successfully updated")).toBeVisible();
            tester.isGoingTo("Refresh the page");
            await page.reload();
            tester.isGoingTo("Validate that the text 'Terminology' is still visible on the page after refresh");
            await expect(page.getByTestId("differentiation-profiles-header-Terminology")).toBeVisible();
            tester.isGoingTo("Verify that the checkbox is still checked");
            await expect(page.getByTestId("differentiation-profiles-toggle")).toBeChecked();
        } finally {
            tester.isGoingTo("Clean up: Reset the toggle to off state");
            try {
                await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
            } catch (error) {
                console.warn("Cleanup failed:", error);
            }
        }
    });
});

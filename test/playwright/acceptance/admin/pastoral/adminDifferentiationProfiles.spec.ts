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
import AdminDifferentiationProfiles from "@schoolboxTest/playwright/support/page/admin/differentiation-profiles/AdminDifferentiationProfiles";

test.describe("Admin Differentiation Profiles", () => {
    test("Toggle differentiation profiles", async ({ tester, page, db }) => {
        const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
        try {
            tester.isGoingTo("Login as admin");
            await tester.loginAsAdmin();
            tester.isGoingTo("Visit the differentiation profiles page");
            await page.goto("/adminv2/pastoral/differentiation-profiles");
            await adminDifferentiationProfiles.toggleOn();
        } finally {
            tester.isGoingTo("Clean up: Reset the toggle to off state");
            try {
                await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
            } catch (error) {
                console.warn("Cleanup failed:", error);
            }
        }
    });

    test("rename field", async ({ tester, page, db }) => {
        const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
        try {
            tester.isGoingTo("Login as admin");
            await tester.loginAsAdmin();
            tester.isGoingTo("Visit the differentiation profiles page");
            await page.goto("/adminv2/pastoral/differentiation-profiles");
            tester.isGoingTo("Toggle the differentiation profiles on");
            await adminDifferentiationProfiles.toggleOn();
            tester.isGoingTo("Find the input with data-test attribute 'differentiation-profiles-input-terminology-1'");
            const terminologyInput = page.getByTestId("differentiation-profiles-input-terminology-1");
            tester.isGoingTo("Update the input text to 'Party time'");
            await terminologyInput.clear();
            await terminologyInput.fill("Party time");
            await adminDifferentiationProfiles.clickSave();
            tester.isGoingTo("Refresh the page");
            await page.reload();
            tester.isGoingTo("Ensure the text 'Party time' is still visible");
            await expect(page.getByTestId("differentiation-profiles-input-terminology-1")).toHaveValue("Party time");
        } finally {
            tester.isGoingTo("Clean up: Reset the field and toggle off");
            try {
                await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
                await db.updateInDatabase(
                    "differentiation_profiles_config",
                    { value: "Adjustment Category" },
                    { "name like": "adjustment_category" },
                );
            } catch (error) {
                console.warn("Cleanup failed:", error);
            }
        }
    });
});

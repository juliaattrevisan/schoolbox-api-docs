/**
 * Tests for the differentiation profiles admin settings functionality
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
    test.beforeEach(async ({ tester, page }) => {
        const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
        tester.isGoingTo("Login as admin");
        await tester.loginAsAdmin();
        await adminDifferentiationProfiles.navigateToPage();
    });

    test("Toggle differentiation profiles", async ({ tester, page, db }) => {
        const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
        try {
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
                    { title: "Adjustment Category" },
                    { "name like": "adjustment_category" },
                );
            } catch (error) {
                console.warn("Cleanup failed:", error);
            }
        }
    });

    test("resequence fields", async ({ tester, page, db }) => {
        const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
        try {
            tester.isGoingTo("Toggle the differentiation profiles on");
            await adminDifferentiationProfiles.toggleOn();
            tester.isGoingTo("grab the drag handle for the Goals field");
            const dragHandle = await page.getByTestId("differentiation-profiles-drag-handle-7");
            await dragHandle.scrollIntoViewIfNeeded();
            await dragHandle.hover();
            await page.mouse.down();
            tester.isGoingTo("drag the Goals field upwards to reorder");
            const container = await page.getByTestId("differentiation-profiles-fieldset-details");
            const box = await container.boundingBox();
            if (box) {
                await page.mouse.move(box.x, box.y + 10, {
                    steps: 30,
                });
            }
            tester.isGoingTo("release the mouse to drop the field in the new position");
            await page.mouse.up();
            await adminDifferentiationProfiles.clickSave();
            tester.isGoingTo("Refresh the page");
            await page.reload();
            tester.isGoingTo("Ensure the Goals field is now in the first position");
            const firstField = container.locator("li").first();
            await expect(firstField).toContainText("Goals");
        } finally {
            tester.isGoingTo("Clean up: Reset the field and toggle off");
            try {
                await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
                await db.updateInDatabase("differentiation_profiles_config", { sequence: 4 }, { "name like": "goals" });
            } catch (error) {
                console.warn("Cleanup failed:", error);
            }
        }
    });
});

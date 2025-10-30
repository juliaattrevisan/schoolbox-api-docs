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
            await adminDifferentiationProfiles.toggleOn();
            await adminDifferentiationProfiles.renameField("terminology", "1", "Party time");
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
            await adminDifferentiationProfiles.toggleOn();
            await adminDifferentiationProfiles.dragToTop("7", "details", "Goals");
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

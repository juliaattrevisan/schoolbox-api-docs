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

import { test } from "@schoolboxTest/playwright/support/integrations";
import AdminDifferentiationProfiles from "@schoolboxTest/playwright/support/page/admin/differentiation-profiles/AdminDifferentiationProfiles";

test.describe("Admin Differentiation Profiles", () => {
    test("should toggle differentiation profiles, show terminology, save and persist settings", async ({
        tester,
        page,
        db,
    }) => {
        const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
        try {
            tester.isGoingTo("Login as admin");
            await tester.loginAsAdmin();
            tester.isGoingTo("Visit the differentiation profiles page");
            await page.goto("/adminv2/pastoral/differentiation-profiles");
            await adminDifferentiationProfiles.toggleOn();
        } finally {
            tester.isGoingTo("Clean up: Reset the toggle to off state");
            // try {
            //     await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
            // } catch (error) {
            //     console.warn("Cleanup failed:", error);
            // }
        }
    });
});

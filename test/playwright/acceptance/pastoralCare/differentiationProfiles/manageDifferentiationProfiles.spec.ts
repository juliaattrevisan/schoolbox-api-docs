/**
 * Tests for the manage differentiation profiles page
 *
 * Prerequisites:
 * 1. Provision sales.tar.gz data
 *
 * Execute: npx playwright test manageDifferentiationProfiles.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import ManageDifferentiationProfiles from "@schoolboxTest/playwright/support/page/pastoralCare/ManageDifferentiationProfiles";
import AdminDifferentiationProfiles from "@schoolboxTest/playwright/support/page/admin/differentiation-profiles/AdminDifferentiationProfiles";

test.describe("Manage Differentiation Profiles", () => {
    test.beforeEach(async ({ tester, page }) => {
        tester.isGoingTo("Login as admin");
        await tester.loginAsAdmin();
    });

    test("Verify the page can't be accessed when profiles are disabled", async ({ tester, page, db }) => {
        const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
        await manageDifferentiationProfiles.navigateToPage();
        tester.isGoingTo("Verify that the page is not found");
        const notFoundPage = page.getByText("Page Not Found");
        await expect(notFoundPage).toBeDefined();
    });

    test("Verify the page can't be accessed without service permission enabled", async ({ tester, page, db }) => {
        try {
            const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
            const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
            await adminDifferentiationProfiles.navigateToPage();
            await adminDifferentiationProfiles.toggleOn();
            await manageDifferentiationProfiles.navigateToPage();
            tester.isGoingTo("Verify that the page is not found");
            const notFoundPage = page.getByText("Page Not Found");
            await expect(notFoundPage).toBeDefined();
        } finally {
            tester.isGoingTo("Clean up: Reset the toggle to off state");
            await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
        }
    });
});

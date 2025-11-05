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

test.describe.configure({ mode: "parallel" });

test.describe("Manage Differentiation Profiles", () => {
    test.beforeEach(async ({ tester }) => {
        tester.isGoingTo("Login as admin");
        await tester.loginAsAdmin();
    });

    test("Verify the page can't be accessed when profiles are disabled", async ({ tester, page }) => {
        const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
        await manageDifferentiationProfiles.navigateToPage();
        tester.isGoingTo("Verify that 'You do not have permission to view this page.' is shown");
        const noPermissionText = page.getByText("You do not have permission to view this page.");
        expect(noPermissionText).toBeVisible();
    });

    test("Verify the page can't be accessed without service permission enabled", async ({ tester, page, db }) => {
        try {
            const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
            const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
            await adminDifferentiationProfiles.navigateToPage();
            await adminDifferentiationProfiles.toggleOn();
            await manageDifferentiationProfiles.navigateToPage();
            tester.isGoingTo("Verify that 'You do not have permission to view this page.' is shown");
            const noPermissionText = page.getByText("You do not have permission to view this page.");
            expect(noPermissionText).toBeVisible();
        } finally {
            tester.isGoingTo("Clean up: Reset the toggle to off state");
            await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
        }
    });

    test("Verify the page can be accessed once profiles are enabled and service permission is granted", async ({
        tester,
        page,
        db,
    }) => {
        try {
            const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
            const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
            await adminDifferentiationProfiles.navigateToPage();
            await adminDifferentiationProfiles.toggleOn();
            tester.isGoingTo("Grant service permission to admin user");
            await db.haveInDatabase("userAccess", { uid: 1, service: 16, sid: 1, type: 2 });
            await tester.loginAsAdmin(); // Re-login to refresh permissions
            await manageDifferentiationProfiles.navigateToPage();
            tester.isGoingTo("Verify that the page is accessible");
            const pageTitle = page.getByText("Manage Differentiation Profiles");
            expect(pageTitle).toBeVisible();
        } finally {
            tester.isGoingTo("Clean up: Reset the toggle to off state");
            await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
            await db.dontHaveInDatabase("userAccess", { uid: 1, service: 16 });
        }
    });
});

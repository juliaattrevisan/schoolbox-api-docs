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
            await expect(pageTitle).toBeVisible();
            tester.isGoingTo("Verify that the empty state is visible");
            const emptyState = page.getByText("No Differentiation Profiles exist");
            await expect(emptyState).toBeVisible();
        } finally {
            tester.isGoingTo("Clean up: Reset the toggle to off state");
            await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
            await db.dontHaveInDatabase("userAccess", { uid: 1, service: 16 });
        }
    });

    test("Verify profiles can be shown", async ({ tester, page, db }) => {
        try {
            const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
            await manageDifferentiationProfiles.enablePage(db);
            await manageDifferentiationProfiles.addPastoralTypes();
            await tester.loginAsAdmin();
            await manageDifferentiationProfiles.navigateToPage();
            tester.isGoingTo("Verify that Mario Izzo and Ms Penelope Li are visible on the page");
            const container = await page.getByTestId("manage-differentiation-profiles-container");
            const marioIzzo = await container.getByText("Mario Izzo");
            const msPenelopeLi = await container.getByText("Ms Penelope Li");
            await expect(marioIzzo).toBeVisible();
            await expect(msPenelopeLi).toBeVisible();
        } finally {
            tester.isGoingTo("Clean up: Reset data state");
            await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
            await db.dontHaveInDatabase("userAccess", { uid: 1, service: 16 });
            await db.updateInDatabase(
                "lu_pastoral_type",
                { contribute_differentiation_profile: 0 },
                { "name like": "Bullying" },
            );
            await db.updateInDatabase(
                "lu_pastoral_subtype",
                { contribute_differentiation_profile: 0 },
                { "name like": "Award/Achievement" },
            );
        }
    });

    test("verify name filter", async ({ tester, page, db }) => {
        try {
            const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
            await manageDifferentiationProfiles.enablePage(db);
            await manageDifferentiationProfiles.addPastoralTypes();
            await tester.loginAsAdmin();
            await manageDifferentiationProfiles.navigateToPage();
            tester.isGoingTo("Click on the name filter button");
            await page.getByTestId("sbx-pop-out-filter-component-36-button").click();
            tester.isGoingTo("Type 'reed' in the name filter input");
            await page.locator('input[name="filter_selection[36][]"]').fill("reed");
            tester.isGoingTo("Click apply filters button");
            await page.getByTestId("panel-apply-filters-button").click();
            await page.getByTestId("panel-apply-filters-button").click(); // click twice as first click closes the filter pop out
            await page.waitForLoadState("load"); // Waits for full page reload
            tester.isGoingTo("Verify that Ms Jana Reed and Mr Leon Reed are visible");
            const container = await page.getByTestId("manage-differentiation-profiles-container");
            const msJanaReed = await container.getByText("Ms Jana Reed");
            const mrLeonReed = await container.getByText("Mr Leon Reed");
            await expect(msJanaReed).toBeVisible();
            await expect(mrLeonReed).toBeVisible();
        } finally {
            tester.isGoingTo("Clean up: Reset data state");
            await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
            await db.dontHaveInDatabase("userAccess", { uid: 1, service: 16 });
            await db.updateInDatabase(
                "lu_pastoral_type",
                { contribute_differentiation_profile: 0 },
                { "name like": "Bullying" },
            );
            await db.updateInDatabase(
                "lu_pastoral_subtype",
                { contribute_differentiation_profile: 0 },
                { "name like": "Award/Achievement" },
            );
        }
    });
});

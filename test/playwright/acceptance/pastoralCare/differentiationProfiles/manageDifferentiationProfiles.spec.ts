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
    test.beforeAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("Turn the differentiation flag off to ensure a consistent starting point for tests");
        await db.updateInDatabase("config", { value: 0 }, { name: "differentiation_profiles_enabled" });
    });

    test.beforeEach(async ({ tester }) => {
        tester.isGoingTo("Login as admin");
        await tester.loginAsAdmin();
    });

    test("Verify the page can't be accessed when Differentiation is disabled", async ({ tester, page }) => {
        const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
        await manageDifferentiationProfiles.navigateToPage();
        tester.isGoingTo("Verify that 'You do not have permission to view this page.' is shown for super users");
        const noPermissionText = page.getByText("You do not have permission to view this page.");
        await expect(noPermissionText).toBeVisible();
    });

    test("Verify the page can't be accessed when user has Differentiation service permission", async ({
        tester,
        page,
        db,
    }) => {
        try {
            const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
            await db.haveInDatabase("userAccess", { uid: 22, service: 16, sid: 1, type: 2 });
            await tester.impersonateTeacher();
            await manageDifferentiationProfiles.navigateToPage();
            const noPermissionText = page.getByText("You do not have permission to view this page.");
            await expect(noPermissionText).toBeVisible();
        } finally {
            tester.isGoingTo("Clean up: Remove service permission from Jack Coles user");
            await db.dontHaveInDatabase("userAccess", { uid: 22, service: 16, sid: 1, type: 2 });
        }
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
            await expect(noPermissionText).toBeVisible();
        } finally {
            tester.isGoingTo("Clean up: Reset the toggle to off state");
            await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
        }
    });

    test("Verify the page can be accessed once Differentiation is enabled and service permission is granted", async ({
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
        const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
        try {
            await manageDifferentiationProfiles.initDefaults(db);
            tester.isGoingTo("Verify that Mario Izzo and Ms Penelope Li are visible on the page");
            const container = page.getByTestId("manage-differentiation-profiles-container");
            const marioIzzo = container.getByText("Izzo, Mario");
            const msPenelopeLi = container.getByText("Li, Penelope");
            await expect(marioIzzo).toBeVisible();
            await expect(msPenelopeLi).toBeVisible();
        } finally {
            await manageDifferentiationProfiles.resetDefaults(db);
        }
    });

    test("verify name filter", async ({ tester, page, db }) => {
        const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
        try {
            await manageDifferentiationProfiles.initDefaults(db);
            tester.isGoingTo("Click on the name filter button");
            await page.getByTestId("sbx-pop-out-filter-component-36-button").click();
            tester.isGoingTo("Type 'reed' in the name filter input");
            await page.locator('input[name="filter_selection[36][]"]').fill("reed");
            tester.isGoingTo("Click apply filters button");
            await page.getByTestId("panel-apply-filters-button").click();
            await page.getByTestId("panel-apply-filters-button").click(); // click twice as first click closes the filter pop out
            await page.waitForLoadState("load"); // Waits for full page reload
            tester.isGoingTo("Verify that Jana Reed and Leon Reed are visible");
            const container = page.getByTestId("manage-differentiation-profiles-container");
            const msJanaReed = container.getByText("Reed, Jana");
            const mrLeonReed = container.getByText("Reed, Leon");
            await expect(msJanaReed).toBeVisible();
            await expect(mrLeonReed).toBeVisible();
        } finally {
            await manageDifferentiationProfiles.resetDefaults(db);
        }
    });

    test("Verify text cell edit", async ({ tester, page, db }) => {
        const studentId = 63; // Mario Izzo
        const fieldKey = "overview";
        const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
        try {
            await manageDifferentiationProfiles.initDefaults(db);
            tester.isGoingTo("Add some text to Mario Izzo's overview field");
            await manageDifferentiationProfiles.clickModifyButton(studentId, fieldKey);
            const textToType = "Mario has a fondness for anything relating to snakes! 🐍";
            await page.keyboard.type(textToType);
            await manageDifferentiationProfiles.clickSaveButton(studentId, fieldKey);
            tester.isGoingTo("Verify that the text is still present after reload");
            await page.reload();
            const overviewCell = page.getByTestId(`lazy-grid-row-cell-0-4-${fieldKey}`);
            await expect(overviewCell).toContainText(textToType);
        } finally {
            await manageDifferentiationProfiles.resetDefaults(db, studentId);
        }
    });

    test("Verify adjustment cell edit", async ({ tester, page, db }) => {
        const studentId = 63; // Mario Izzo
        const fieldKey = "adjustment_category";
        const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
        try {
            await manageDifferentiationProfiles.initDefaults(db);
            tester.isGoingTo("Add some adjustment to the category field");
            await manageDifferentiationProfiles.clickModifyButton(studentId, fieldKey);
            await page.click('label[for="checkbox-1-adjustment_category-63"]');
            await page.click('label[for="checkbox-3-adjustment_category-63"]');
            await manageDifferentiationProfiles.clickSaveButton(studentId, fieldKey);
            tester.isGoingTo("Verify that the adjustment is still present after reload");
            await page.reload();
            const overviewCell = page.getByTestId(`lazy-grid-row-cell-0-2-${fieldKey}`);
            tester.isGoingTo("Verify that can see a list of adjustments added");
            await expect(overviewCell).toContainText("Cognitive");
            await expect(overviewCell).toContainText("Social / Emotional");
        } finally {
            await manageDifferentiationProfiles.resetDefaults(db, studentId);
        }
    });
});

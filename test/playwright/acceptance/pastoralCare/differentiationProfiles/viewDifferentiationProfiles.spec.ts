/**
 * Tests for the view differentiation profiles modal that currently appears on custom participation and class list pages
 *
 * Prerequisites:
 * 1. Provision sales.tar.gz data
 *
 * Execute: npx playwright test viewDifferentiationProfiles.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import ManageDifferentiationProfiles from "@schoolboxTest/playwright/support/page/pastoralCare/ManageDifferentiationProfiles";
import AdminDifferentiationProfiles from "@schoolboxTest/playwright/support/page/admin/differentiation-profiles/AdminDifferentiationProfiles";

test.describe.configure({ mode: "serial" });

test.describe("Manage Differentiation Profiles", () => {
    test.beforeAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("Turn the differentiation flag off to ensure a consistent starting point for tests");
        await db.updateInDatabase("config", { value: 0 }, { name: "differentiation_profiles_enabled" });
    });

    test.beforeEach(async ({ tester }) => {
        tester.isGoingTo("Login as admin");
        await tester.loginAsAdmin();
    });

    test("Verify modal visibility on custom participation", async ({ tester, page, db }) => {
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
            tester.isGoingTo("Visit Student Participation - TKAM Final Assessment: Essay");
            await page.goto("/learning/assessments/659/participation");
            tester.isGoingTo("Open the differentiation profile modal for Mario Izzo");
            const viewDpButton = page.getByTestId(`cp-participant-${studentId}-view-differentiation-profile`);
            await viewDpButton.click();
            tester.isGoingTo("Verify that the differentiation profile modal is visible and contains the text added");
            const dpModal = page.getByTestId("view-dp-modal-fields-container");
            await expect(dpModal).toBeVisible();
            await expect(dpModal).toContainText(textToType);
            tester.isGoingTo(
                "confirm Mario's DP is the only one shown by confirming the text 'View Differentiation Profile' is only shown once on the page",
            );
            const viewDpTexts = page.getByText("View Differentiation Profile");
            await expect(viewDpTexts).toHaveCount(1);
        } finally {
            await manageDifferentiationProfiles.resetDefaults(db, studentId);
        }
    });

    test("Verify modal not shown on custom participation when DP is turned off", async ({ tester, page, db }) => {
        const studentId = 63; // Mario Izzo
        const fieldKey = "overview";
        const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
        const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
        try {
            await manageDifferentiationProfiles.initDefaults(db);
            tester.isGoingTo("Add some text to Mario Izzo's overview field");
            await manageDifferentiationProfiles.clickModifyButton(studentId, fieldKey);
            const textToType = "Mario has a fondness for anything relating to snakes! 🐍";
            await page.keyboard.type(textToType);
            await manageDifferentiationProfiles.clickSaveButton(studentId, fieldKey);
            tester.isGoingTo("Turn off differentiation profiles from admin page");
            await adminDifferentiationProfiles.navigateToPage();
            await adminDifferentiationProfiles.toggleOff();
            tester.isGoingTo("Visit Student Participation - TKAM Final Assessment: Essay");
            await page.goto("/learning/assessments/659/participation");
            tester.isGoingTo("Verify that the differentiation profile modal button is not shown for Mario Izzo");
            const viewDpButton = page.getByTestId(`cp-participant-${studentId}-view-differentiation-profile`);
            await expect(viewDpButton).not.toBeVisible();
        } finally {
            await manageDifferentiationProfiles.resetDefaults(db, studentId);
        }
    });

    test("Verify modal visibility on class list", async ({ tester, page, db }) => {
        const studentId = 25; // Jana Reed
        const fieldKey = "goals";
        const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
        try {
            await manageDifferentiationProfiles.initDefaults(db);
            tester.isGoingTo("Add some text to Jana Reed's goals field");
            await manageDifferentiationProfiles.clickModifyButton(studentId, fieldKey);
            const textToType = "Jana wants to improve her public speaking skills.";
            await page.keyboard.type(textToType);
            await manageDifferentiationProfiles.clickSaveButton(studentId, fieldKey);
            tester.isGoingTo("Visit 9A English (9ENGA) class list");
            await page.goto("/learning/class/83");
            tester.isGoingTo("Open the differentiation profile modal for Jana Reed");
            const viewDpButton = page.getByTestId(`class-list-${studentId}-view-differentiation-profile`);
            await viewDpButton.click();
            tester.isGoingTo("Verify that the differentiation profile modal is visible and contains the text added");
            const dpModal = page.getByTestId("view-dp-modal-fields-container");
            await expect(dpModal).toBeVisible();
            await expect(dpModal).toContainText(textToType);
            tester.isGoingTo(
                "confirm Jana's DP is the only one shown by confirming the text 'View Differentiation Profile' is only shown once on the page",
            );
            const viewDpTexts = page.getByText("View Differentiation Profile");
            await expect(viewDpTexts).toHaveCount(1);
        } finally {
            await manageDifferentiationProfiles.resetDefaults(db, studentId);
        }
    });

    test("Verify modal not shown on class list when DP is turned off", async ({ tester, page, db }) => {
        const studentId = 29; // Penelope Li
        const fieldKey = "needs_challenges";
        const manageDifferentiationProfiles = new ManageDifferentiationProfiles(tester, page);
        const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
        try {
            await manageDifferentiationProfiles.initDefaults(db);
            tester.isGoingTo("Add some text to Penelope Li's needs_challenges field");
            await manageDifferentiationProfiles.clickModifyButton(studentId, fieldKey);
            const textToType = "Penelope needs more support in math.";
            await page.keyboard.type(textToType);
            await manageDifferentiationProfiles.clickSaveButton(studentId, fieldKey);
            tester.isGoingTo("Turn off differentiation profiles from admin page");
            await adminDifferentiationProfiles.navigateToPage();
            await adminDifferentiationProfiles.toggleOff();
            tester.isGoingTo("Visit 9A English (9ENGA) class list");
            await page.goto("/learning/class/83");
            tester.isGoingTo("Verify that the differentiation profile modal button is not shown for Penelope Li");
            const viewDpButton = page.getByTestId(`class-list-${studentId}-view-differentiation-profile`);
            await expect(viewDpButton).not.toBeVisible();
        } finally {
            await manageDifferentiationProfiles.resetDefaults(db, studentId);
        }
    });
});

/**
 * Tests for the differentiation profiles admin settings functionality
 *
 * Prerequisites:
 * 1. Provision sales.tar.gz data
 *
 * Execute: npx playwright test adminDifferentiationProfiles.spec.ts
 */

import { test } from "@schoolboxTest/playwright/support/integrations";
import AdminDifferentiationProfiles from "@schoolboxTest/playwright/support/page/admin/differentiation-profiles/AdminDifferentiationProfiles";

test.describe.configure({ mode: "serial" });

test.describe("Admin Differentiation Profiles", () => {
    test.beforeAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("Turn the differentiation flag off to ensure a consistent starting point for tests");
        await db.updateInDatabase("config", { value: 0 }, { name: "differentiation_profiles_enabled" });
    });

    test.beforeEach(async ({ tester, page }) => {
        const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
        tester.isGoingTo("Login as admin");
        await tester.loginAsAdmin();
        await adminDifferentiationProfiles.navigateToPage();
    });

    test("Toggle differentiation profiles on", async ({ tester, page, db }) => {
        const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
        try {
            await adminDifferentiationProfiles.toggleOn();
        } finally {
            tester.isGoingTo("Clean up: Reset the toggle to off state");
            await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
        }
    });

    test("rename field", async ({ tester, page, db }) => {
        const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
        try {
            await adminDifferentiationProfiles.toggleOn();
            await adminDifferentiationProfiles.renameField("terminology", "1", "Party time");
        } finally {
            tester.isGoingTo("Clean up: Reset the field and toggle off");
            await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
            await db.updateInDatabase(
                "differentiation_profiles_config",
                { title: "Adjustment Category" },
                { "name like": "adjustment_category" },
            );
        }
    });

    test("resequence fields", async ({ tester, page, db }) => {
        const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
        try {
            await adminDifferentiationProfiles.toggleOn();
            await adminDifferentiationProfiles.dragToTop("7", "details", "Goals");
        } finally {
            tester.isGoingTo("Clean up: Reset the field and toggle off");
            await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
            await db.updateInDatabase("differentiation_profiles_config", { sequence: 4 }, { "name like": "goals" });
        }
    });

    test("disable field", async ({ tester, page, db }) => {
        const adminDifferentiationProfiles = new AdminDifferentiationProfiles(tester, page);
        try {
            await adminDifferentiationProfiles.toggleOn();
            await adminDifferentiationProfiles.disableField("is_enabled", "3");
        } finally {
            tester.isGoingTo("Clean up: Reset the field and toggle off");
            await db.updateInDatabase("config", { value: 0 }, { "name like": "differentiation_profiles_enabled" });
            await db.updateInDatabase(
                "differentiation_profiles_config",
                { is_enabled: 1 },
                { "name like": "overview" },
            );
        }
    });
});

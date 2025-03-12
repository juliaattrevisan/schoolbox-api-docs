/**
 * Test the pastoral care modules expiry feature
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test manageRecordSettings.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import ManageRecordSettings from "@schoolboxTest/playwright/support/page/pastoralCare/ManageRecordSettings";

test.describe("Curriculum Coverage Report Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.impersonateTeacher();
    });

    test("Generate a report", async ({ tester, page }) => {
        const manageRecordSettings = new ManageRecordSettings(page);

        tester.isGoingTo("Visit the Manage Award/Achievement page");
        await manageRecordSettings.gotoAwardAchievement();

        tester.isGoingTo("Select Achievement flag");
        await manageRecordSettings.selectFlag("Achievement");

        tester.isGoingTo("Set the flag color to green");
        await manageRecordSettings.fillFlagColor("#00ff1e");

        tester.isGoingTo("Set the default expiry to 1 Month");
        await manageRecordSettings.selectDefaultExpiry("1 Month");

        tester.isGoingTo("Save the changes");
        await manageRecordSettings.clickSave();
    });
});

/**
 * Test the pastoral care modules expiry feature
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test pastoralExpiry.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import PastoralExpiry from "@schoolboxTest/playwright/support/page/pastoralCare/expiry/PastoralExpiry";

test.describe("Curriculum Coverage Report Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.impersonateTeacher();
    });

    test("Generate a report", async ({ tester, page }) => {
        const pastoralExpiry = new PastoralExpiry(page);

        tester.isGoingTo("Visit the Manage Award/Achievement page");
        await pastoralExpiry.gotoManageAwardAchievement();

        tester.isGoingTo("Select Achievement flag");
        await pastoralExpiry.selectFlag("Achievement");

        tester.isGoingTo("Set the flag color to green");
        await pastoralExpiry.fillFlagColor("#00ff1e");

        tester.isGoingTo("Set the default expiry to 1 Month");
        await pastoralExpiry.selectDefaultExpiry("1 Month");

        tester.isGoingTo("Save the changes");
        await pastoralExpiry.clickSave();
    });
});

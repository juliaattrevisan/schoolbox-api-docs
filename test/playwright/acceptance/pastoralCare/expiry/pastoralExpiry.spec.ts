/**
 * Test the pastoral care modules expiry feature
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test manageRecordSettings.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import ManageRecordSettings from "@schoolboxTest/playwright/support/page/pastoralCare/ManageRecordSettings";
import CreatePastoralRecord from "@schoolboxTest/playwright/support/page/pastoralCare/CreatePastoralRecord";
import dayjs from "dayjs";

test.describe("Test the pastoral care modules expiry feature", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.impersonateTeacher();
    });

    test("Update the Academic > Award/Achievement type with a default expiry of 1 Month", async ({ tester, page }) => {
        const manageRecordSettings = new ManageRecordSettings(page);

        tester.isGoingTo("Visit the Award/Achievement page");
        await manageRecordSettings.gotoAwardAchievement();

        tester.isGoingTo("Select Achievement flag");
        await manageRecordSettings.selectFlag("Achievement");

        tester.isGoingTo("Set the flag color to green");
        await manageRecordSettings.fillFlagColor("#00ff1e");

        tester.isGoingTo("Set the default expiry to 1 Month");
        await manageRecordSettings.selectDefaultExpiry("1 Month");

        tester.isGoingTo("Save the changes");
        await manageRecordSettings.clickSave();

        await page.reload();

        tester.isGoingTo("Confirm the changes have been saved");
        await manageRecordSettings.expectDefaultExpiryToBe("1 month");
    });

    test("Update the Academic > Learning Difficulties type with a default expiry of 6 Months", async ({
        tester,
        page,
    }) => {
        const manageRecordSettings = new ManageRecordSettings(page);

        tester.isGoingTo("Visit the Learning Difficulties page");
        await manageRecordSettings.gotoLearningDifficulties();

        tester.isGoingTo("Select Alert flag");
        await manageRecordSettings.selectFlag("Alert");

        tester.isGoingTo("Set the flag color to red");
        await manageRecordSettings.fillFlagColor("#ff0000");

        tester.isGoingTo("Set the default expiry to 6 Months");
        await manageRecordSettings.selectDefaultExpiry("6 Months");

        tester.isGoingTo("Save the changes");
        await manageRecordSettings.clickSave();

        await page.reload();

        tester.isGoingTo("Confirm the changes have been saved");
        await manageRecordSettings.expectDefaultExpiryToBe("6 months");
    });

    test("Update the Attendance type with a default expiry of 2 weeks", async ({ tester, page }) => {
        const manageRecordSettings = new ManageRecordSettings(page);

        tester.isGoingTo("Visit the Attendance page");
        await manageRecordSettings.gotoAttendance();

        tester.isGoingTo("Set the default expiry to 2 Weeks");
        await manageRecordSettings.selectDefaultExpiry("2 Weeks");

        tester.isGoingTo("Save the changes");
        await manageRecordSettings.clickSave();

        await page.reload();

        tester.isGoingTo("Confirm the changes have been saved");
        await manageRecordSettings.expectDefaultExpiryToBe("2 weeks");
    });

    test("Check the expires at changes based on the current type/subtype selection", async ({ tester, page }) => {
        const createPastoralRecord = new CreatePastoralRecord(page);

        tester.isGoingTo("Add a new pastoral record to Jana Reed");
        await createPastoralRecord.goto(25);

        tester.isGoingTo("Set type to Academic > Award Achievement");
        await createPastoralRecord.selectType("1");

        tester.isGoingTo("Check expires at date is 1 month from today");
        const dateOneMonth = dayjs().add(1, "month").format("DD/MM/YYYY");
        createPastoralRecord.expectExpiresAtToBe(dateOneMonth);

        tester.isGoingTo("Set sub type to Academic > Learning Difficulties");
        await createPastoralRecord.selectSubType("2");

        tester.isGoingTo("Check expires at date is 6 month from today");
        const dateSixMonths = dayjs().add(6, "month").format("DD/MM/YYYY");
        createPastoralRecord.expectExpiresAtToBe(dateSixMonths);

        tester.isGoingTo("Set type to Attendance");
        await createPastoralRecord.selectType("2");

        tester.isGoingTo("Check expires at date is 6 month from today");
        const date2Weeks = dayjs().add(2, "weeks").format("DD/MM/YYYY");
        createPastoralRecord.expectExpiresAtToBe(date2Weeks);
    });
});

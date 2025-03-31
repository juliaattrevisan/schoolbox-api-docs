/**
 * Test the pastoral care modules expiry feature
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test pastoralExpiry.spec.ts
 */

import dayjs from "@js/dayjs/dayjsWithPlugins";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import ManageRecordSettings from "@schoolboxTest/playwright/support/page/pastoralCare/ManageRecordSettings";
import CreatePastoralRecord from "@schoolboxTest/playwright/support/page/pastoralCare/CreatePastoralRecord";
import PastoralProfile from "@schoolboxTest/playwright/support/page/pastoralCare/PastoralProfile";
import ClassReport from "@schoolboxTest/playwright/support/page/pastoralCare/ClassReport";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";

test.describe("Test the pastoral care modules expiry feature", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.impersonateTeacher();
    });

    test("Confirm default expiry changes are saved", async ({ tester, page, db }) => {
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

        await db.updateInDatabase("lu_pastoral_type", { default_expiry: null }, { name: "Attendance" });
    });

    test("Check type/subtype changes just the expires at", async ({ tester, page, db }) => {
        const manageRecordSettings = new ManageRecordSettings(page);
        const createPastoralRecord = new CreatePastoralRecord(page);

        tester.isGoingTo("Visit the Award/Achievement page");
        await manageRecordSettings.gotoAwardAchievement();

        tester.isGoingTo("Set the default expiry to 1 Month");
        await manageRecordSettings.selectDefaultExpiry("1 Month");

        tester.isGoingTo("Save the changes");
        await manageRecordSettings.clickSave();

        tester.isGoingTo("Visit the Learning Difficulties page");
        await manageRecordSettings.gotoLearningDifficulties();

        tester.isGoingTo("Set the default expiry to 6 Months");
        await manageRecordSettings.selectDefaultExpiry("6 Months");

        tester.isGoingTo("Save the changes");
        await manageRecordSettings.clickSave();

        tester.isGoingTo("Visit the Attendance page");
        await manageRecordSettings.gotoAttendance();

        tester.isGoingTo("Set the default expiry to 2 Weeks");
        await manageRecordSettings.selectDefaultExpiry("2 Weeks");

        tester.isGoingTo("Save the changes");
        await manageRecordSettings.clickSave();

        tester.isGoingTo("Add a new pastoral record to Jana Reed");
        await createPastoralRecord.goto(25);

        tester.isGoingTo("Set type to Academic > Award Achievement");
        await createPastoralRecord.selectType("1");

        tester.isGoingTo("Check expires at date is 1 month from today");
        const dateOneMonth = dayjs.parseNatural("in 1 month"); // use parseNatural as it aligns with the backend's datetime logic
        if (dateOneMonth === false) throw new Error("Failed to parse date");
        await createPastoralRecord.expectExpiresAtToBe(dateOneMonth.format("DD/MM/YYYY"));

        tester.isGoingTo("Set sub type to Academic > Learning Difficulties");
        await createPastoralRecord.selectSubType("2");

        tester.isGoingTo("Check expires at date is 6 month from today");
        const dateSixMonths = dayjs.parseNatural("in 6 months");
        if (dateSixMonths === false) throw new Error("Failed to parse date");
        await createPastoralRecord.expectExpiresAtToBe(dateSixMonths.format("DD/MM/YYYY"));

        tester.isGoingTo("Set type to Attendance");
        await createPastoralRecord.selectType("2");

        tester.isGoingTo("Check expires at date is 2 weeks from today");
        const date2Weeks = dayjs.parseNatural("in 2 weeks");
        if (date2Weeks === false) throw new Error("Failed to parse date");
        await createPastoralRecord.expectExpiresAtToBe(date2Weeks.format("DD/MM/YYYY"));

        await db.updateInDatabase("lu_pastoral_type", { default_expiry: null }, { name: "Attendance" });
        await db.updateInDatabase("lu_pastoral_type", { default_expiry: null }, { name: "Bullying" });
        await db.updateInDatabase("lu_pastoral_subtype", { default_expiry: null }, { name: "Learning Difficulties" });
    });

    test("Check manual date entry takes precedence over type/subtype selection", async ({ tester, page, db }) => {
        const manageRecordSettings = new ManageRecordSettings(page);
        const createPastoralRecord = new CreatePastoralRecord(page);

        tester.isGoingTo("Visit the Award/Achievement page");
        await manageRecordSettings.gotoAwardAchievement();

        tester.isGoingTo("Set the default expiry to 1 Month");
        await manageRecordSettings.selectDefaultExpiry("1 Month");

        tester.isGoingTo("Save the changes");
        await manageRecordSettings.clickSave();

        tester.isGoingTo("Add a new pastoral record to Jana Reed");
        await createPastoralRecord.goto(25);

        tester.isGoingTo("Set the expires at date to 01/01/2050");
        const date = "01/01/2050";
        await createPastoralRecord.setExpiresAt(date);

        tester.isGoingTo("Set type to Academic > Award Achievement");
        await createPastoralRecord.selectType("1");

        tester.isGoingTo("Check expires at date is still 01/01/2050");
        await createPastoralRecord.expectExpiresAtToBe(date);

        await db.updateInDatabase("lu_pastoral_subtype", { default_expiry: null }, { name: "Award/Achievement" });
    });

    test("Check Apply to existing records updates existing records", async ({ tester, page, db }) => {
        const manageRecordSettings = new ManageRecordSettings(page);
        const createPastoralRecord = new CreatePastoralRecord(page);

        await createPastoralRecord.gotoExisting(25, 2);
        await createPastoralRecord.expectExpiresAtToBe("");

        tester.isGoingTo("Visit the manage Bullying page");
        await manageRecordSettings.gotoBullying();

        tester.isGoingTo("Set the default expiry to 1 Month");
        await manageRecordSettings.selectDefaultExpiry("1 Month");

        tester.isGoingTo("Check the Apply Default Expiry checkbox");
        await manageRecordSettings.clickApplyDefaultExpiry();

        tester.isGoingTo("Save the changes");
        await manageRecordSettings.clickSave();

        tester.isGoingTo(
            "Check an existing Bullying record and confirm the expiry date is 1 month from the created date",
        );
        await createPastoralRecord.gotoExisting(25, 2);
        await createPastoralRecord.expectExpiresAtToBe("01/12/2017");

        await db.updateInDatabase("lu_pastoral_type", { default_expiry: null }, { name: "Bullying" });
        await db.updateInDatabase("pastoral", { expires_at: null }, { heading: "Cyber Bullying" });
    });

    test("Check the show archived records toggle on a users pastoral profile page", async ({ tester, page, db }) => {
        const pastoralProfile = new PastoralProfile(page);
        const createPastoralRecord = new CreatePastoralRecord(page);

        tester.isGoingTo("Visit the pastoral profile page for Jana Reed");
        await pastoralProfile.goto(25);

        tester.isGoingTo("Check that three records are visible");
        expect(await pastoralProfile.getRecords()).toHaveLength(3);

        tester.isGoingTo("Modify the expired at date of the 'Out of uniform' record to 2010");
        await pastoralProfile.clickModifyIcon(25, 1);
        await createPastoralRecord.setExpiresAt("01/01/2010");
        await createPastoralRecord.clickSave();

        tester.isGoingTo("Check that two records are now visible");
        await pastoralProfile.goto(25);
        expect(await pastoralProfile.getRecords()).toHaveLength(2);

        tester.isGoingTo("Toggle the show archived records and check that all three records are visible again");
        await pastoralProfile.clickShowArchivedRecordsToggle();
        expect(await pastoralProfile.getRecords()).toHaveLength(3);

        await db.updateInDatabase("pastoral", { expires_at: null }, { heading: "Out of uniform" });
    });

    test("Confirm expired records are hidden by default on the class Pastoral Care Report", async ({
        tester,
        page,
        db,
    }) => {
        const pastoralProfile = new PastoralProfile(page);
        const createPastoralRecord = new CreatePastoralRecord(page);
        const classReport = new ClassReport(page);

        tester.isGoingTo("Visit the pastoral profile page for Jana Reed");
        await pastoralProfile.goto(25);

        tester.isGoingTo("Modify the expired at date of the 'Out of uniform' record to 2010");
        await pastoralProfile.clickModifyIcon(25, 1);
        await createPastoralRecord.setExpiresAt("01/01/2010");
        await createPastoralRecord.clickSave();

        tester.isGoingTo("Visit the 9A English (9ENGA) class pastoral care report");
        await classReport.goto(83);

        tester.isGoingTo("Check that the 'Out of uniform' record is not visible by default");
        const studentRecord = classReport.getStudentRecord(25);
        await expect(studentRecord).not.toContainText("Out of uniform");

        tester.isGoingTo("Add the Status > Archived filter and check that the 'Out of uniform' record is visible");
        await classReport.clickStatusFilter();
        await classReport.clickArchivedCheckbox();
        await classReport.clickApplyFiltersButton();
        await page.waitForLoadState("load"); // Waits for full page reload
        await expect(studentRecord).toContainText("Out of uniform");

        await db.updateInDatabase("pastoral", { expires_at: null }, { heading: "Out of uniform" });
    });

    test("Confirm expired pastoral flags do not appear on student profile", async ({ tester, page, db }) => {
        const manageRecordSettings = new ManageRecordSettings(page);
        const profilePage = new ProfilePage(tester, page);

        tester.isGoingTo("Visit the Award/Achievement page");
        await manageRecordSettings.gotoAwardAchievement();

        tester.isGoingTo("Select Achievement flag");
        await manageRecordSettings.selectFlag("Achievement");

        tester.isGoingTo("Set the flag color to green");
        await manageRecordSettings.fillFlagColor();

        tester.isGoingTo("Save the changes");
        await manageRecordSettings.clickSave();

        tester.isGoingTo("Visit the profile page for Penelope Li");
        await profilePage.goto(29);

        tester.isGoingTo("Check that the Achievement flag is visible");
        await expect(profilePage.getFlag(1, 1)).toBeVisible();

        tester.isGoingTo("Visit the manage Award/Achievement page");
        await manageRecordSettings.gotoAwardAchievement();

        tester.isGoingTo("Set the default expiry to 1 Month");
        await manageRecordSettings.selectDefaultExpiry("1 Month");

        tester.isGoingTo("Check the Apply Default Expiry checkbox");
        await manageRecordSettings.clickApplyDefaultExpiry();

        tester.isGoingTo("Save the changes");
        await manageRecordSettings.clickSave();

        tester.isGoingTo("Visit the profile page for Penelope Li again");
        await profilePage.goto(29);

        tester.isGoingTo("Check that the Achievement flag is no longer visible");
        await expect(profilePage.getFlag(1, 1)).not.toBeVisible();

        await db.updateInDatabase(
            "lu_pastoral_subtype",
            { color: null, icon: null, default_expiry: null },
            { name: "Award/Achievement" },
        );
        await db.updateInDatabase(
            "pastoral",
            { expires_at: null },
            { heading: "Academic Achievement Award - Semester 2" },
        );
    });
});

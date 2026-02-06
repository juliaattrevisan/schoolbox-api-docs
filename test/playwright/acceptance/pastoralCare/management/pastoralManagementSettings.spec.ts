import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import PastoralProfile from "@schoolboxTest/playwright/support/page/pastoralCare/PastoralProfile";
import CreatePastoralRecord from "@schoolboxTest/playwright/support/page/pastoralCare/CreatePastoralRecord";
import PastoralExpiry from "@schoolboxTest/playwright/support/page/pastoralCare/ManageRecordSettings";
import Common from "@schoolboxTest/playwright/support/page/Common";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";
import ClassList from "@schoolboxTest/playwright/support/page/homepage/pages/ClassList";
import StudentParticipation from "@schoolboxTest/playwright/support/page/studentParticipation/StudentParticipation";

/**
 * navigate to pastoral care management settings
 * set up some record visibility settings for 2 types
 * lock it for one of them
 * check the non pastoral moderator cannot change the locked visibility
 * check the pastoral moderator can change the locked visibility
 * set up sentiment, tip, general and confidential templates
 * check the tip and templates are available when creating a pastoral record
 */

test.describe("Pastoral Care - Management Settings", () => {
    let pastoralProfile: PastoralProfile;
    let createPastoralRecord: CreatePastoralRecord;
    let pastoralRecordSettings: PastoralExpiry;
    let common: Common;
    let profilePage: ProfilePage;
    let classList: ClassList;
    let studentParticipation: StudentParticipation;

    test.beforeEach(async ({ page, tester }) => {
        pastoralProfile = new PastoralProfile(page, tester);
        createPastoralRecord = new CreatePastoralRecord(page);
        // login as admin
        await tester.loginAsAdmin();
    });

    test("set and check pastoral care management settings: lock visibility", async ({ page, tester, db }) => {
        pastoralRecordSettings = new PastoralExpiry(page, tester);
        common = new Common(page);
        createPastoralRecord = new CreatePastoralRecord(page);
        const studentId = 25;
        const pastoralTitle1 = "Automation Test Pastoral Record";
        const pastoralTitle2 = "Automation Test Locked Pastoral Record";
        // navigate to pastoral care management settings as a pastoral moderator
        tester.isGoingTo("navigate to pastoral care management settings as a pastoral moderator");
        await tester.impersonate("Jack Coles");
        await pastoralRecordSettings.gotoAwardAchievement();
        // set up some record visibility settings for a type and not locking it
        tester.isGoingTo("set up some record visibility settings for a type/subtype and lock it for one of them");
        await pastoralRecordSettings.clickVisibilityRoleLink();
        await pastoralRecordSettings.clickRolesInModal(["Administration", "Teacher", "parent"]);
        await common.waitForModalToBeClosed();
        await pastoralRecordSettings.clickSave();
        // set up some record visibility settings for 2 types and lock it for one of them
        tester.isGoingTo("set up some record visibility settings for a type and lock it for one of them");
        await pastoralRecordSettings.gotoBullying();
        await pastoralRecordSettings.clickVisibilityRoleLink();
        await pastoralRecordSettings.clickRolesInModal(["Administration", "Teacher", "parent"]);
        await common.waitForModalToBeClosed();
        await pastoralRecordSettings.clickLockVisibilityToggle();
        await pastoralRecordSettings.clickSave();

        // create a pastoral record of each type

        await tester.loginAsAdmin();
        tester.isGoingTo(
            "create a pastoral record as alaress and check a non pastoral moderator can edit the settings",
        );
        await createPastoralRecord.goto(studentId);
        await createPastoralRecord.typeTitle(pastoralTitle1);
        tester.isGoingTo(
            "check that selecting a type without any settings set up in the manage settings shows the empty state",
        );
        await createPastoralRecord.clickTypeDropdown();
        await createPastoralRecord.selectType("2");
        await expect(page.locator("#visibility-empty")).toBeVisible();
        await expect(page.locator("#visibility_empty_state")).toBeVisible();
        tester.isGoingTo("select a type with settings set up in the manage settings and editing the visibility");
        await createPastoralRecord.clickTypeDropdown();
        await createPastoralRecord.selectType("1");
        await pastoralRecordSettings.clickDenyParent();
        await createPastoralRecord.clickSave();
        const recordId1 = (await db.grabFromDatabase("pastoral", "id", {
            deleted_at: null,
            "heading like": pastoralTitle1,
        })) as number;
        tester.isGoingTo("check the content visibility is correct in the pastoral record just created");
        await pastoralProfile.clickPastoralRecord(studentId, recordId1);
        await expect(page.locator("ul.information-list.threaded")).toContainText("Denied Access");

        tester.isGoingTo("create a pastoral record as alaress and check the record visibility settings is locked");
        await createPastoralRecord.goto(studentId);
        await createPastoralRecord.clickTypeDropdown();
        await createPastoralRecord.selectType("4");
        await createPastoralRecord.typeTitle(pastoralTitle2);
        await expect(page.locator(`label[for="roleDeny1-locked-locked-locked"]`)).toBeVisible();
        await expect(page.locator(`label[for="rolePublic2-locked-locked"]`)).toBeVisible();
        await expect(page.locator(`label[for="rolePrivaterole-type-parent-locked"]`)).toBeVisible();
        await createPastoralRecord.clickSave();
        const recordId2 = (await db.grabFromDatabase("pastoral", "id", {
            deleted_at: null,
            "heading like": pastoralTitle2,
        })) as number;

        tester.isGoingTo(
            "check the pastoral moderator can edit the locked settings and set confidential comment for administrators",
        );
        await tester.impersonate("Jack Coles");
        await pastoralProfile.goto(studentId);
        await pastoralProfile.clickModifyIcon(studentId, recordId2);
        await expect(page.locator(`a[data-button="add-group"]`)).toBeVisible();
        await expect(page.locator(`label[for="roleDeny1-locked-locked-locked"]`)).not.toBeVisible();
        await expect(page.locator(`label[for="rolePublic2-locked-locked"]`)).not.toBeVisible();
        await expect(page.locator(`label[for="rolePrivaterole-type-parent-locked"]`)).not.toBeVisible();
        await pastoralRecordSettings.clickConfidentialComment(2);
        await createPastoralRecord.clickSave();
        tester.isGoingTo("check the content visibility is correct in the pastoral record just created");
        await pastoralProfile.clickPastoralRecord(studentId, recordId2);
        await expect(page.locator("ul.information-list.threaded")).toContainText("Confidential");
        tester.isGoingTo("check rebecca white can see the confidential comment tab");

        await tester.impersonate("Rebecca White");
        await pastoralProfile.goto(studentId);
        await pastoralProfile.clickPastoralRecord(studentId, recordId2);
        await expect(page.locator("a#panel-confidential")).toBeVisible();
        await pastoralProfile.goto(studentId);
        await pastoralProfile.clickPastoralRecord(studentId, recordId1);
        await expect(page.locator("#panel-confidential")).not.toBeVisible();
        tester.isGoingTo("as a parent check they can only see the pastoral record they have access to");
        await tester.impersonate("Jenni Reed");
        await pastoralProfile.goto(studentId);
        await expect(page.locator("#pastoralRecords")).not.toContainText(pastoralTitle1);
        await expect(page.locator("#pastoralRecords")).toContainText(pastoralTitle2);
    });

    test.skip("set and check pastoral care management settings: sentiment, tip, general and confidential templates", async ({
        page,
        tester,
        db,
    }) => {
        pastoralRecordSettings = new PastoralExpiry(page, tester);
        common = new Common(page);
        createPastoralRecord = new CreatePastoralRecord(page);
        const studentId = 25;
        const pastoralTitle = "Automation Test Pastoral Record Sentiment, Tip and Templates";
        const tipTemplateAcademic = "This is a tip for the pastoral record academic type";
        const generalTemplateAcademic = "This is a general template for the pastoral record academic type";
        const confidentialTemplateAcademic = "This is a confidential template for the pastoral record academic type";
        const tipTemplateAttendance = "This is a tip for the pastoral record attendance type";
        const generalTemplateAttendance = "This is a general template for the pastoral record attendance type";
        const confidentialTemplateAttendance =
            "This is a confidential template for the pastoral record attendance type";
        // navigate to pastoral care management settings as a pastoral moderator
        tester.isGoingTo("navigate to pastoral care management settings as a pastoral moderator");
        await tester.impersonate("Jack Coles");
        await pastoralRecordSettings.gotoAwardAchievement();
        // set up sentiment, tip, general and confidential templates
        tester.isGoingTo("set up sentiment, tip, general and confidential templates");
        await pastoralRecordSettings.selectSentiment("Positive");
        await pastoralRecordSettings.fillEntryTip(tipTemplateAcademic);
        await pastoralRecordSettings.fillGeneralTemplate(generalTemplateAcademic);
        await pastoralRecordSettings.fillPrivateTemplate(confidentialTemplateAcademic);
        await pastoralRecordSettings.clickSave();
        // checking the sentiment setting is saved as this is not used anywhere else yet - only Intellischools are using it
        await page.reload();
        await expect(page.locator("select#sentiment")).toContainText("Positive");
        await pastoralRecordSettings.gotoAttendance();
        await pastoralRecordSettings.fillEntryTip(tipTemplateAttendance);
        await pastoralRecordSettings.fillGeneralTemplate(generalTemplateAttendance);
        await pastoralRecordSettings.fillPrivateTemplate(confidentialTemplateAttendance);
        await pastoralRecordSettings.clickSave();

        // create a pastoral record and check the templates are available
        await tester.loginAsAdmin();
        tester.isGoingTo("create a pastoral record as alaress and check the templates are available");
        await createPastoralRecord.goto(studentId);
        await createPastoralRecord.typeTitle(pastoralTitle);
        tester.isGoingTo("check the attendance type shows the proper tip");
        await createPastoralRecord.clickTypeDropdown();
        await createPastoralRecord.selectType("2");
        await createPastoralRecord.verifyEntryTip(tipTemplateAttendance);
        // checking the templates once the record is created to avoid interacting too much with the ckeditor
        tester.isGoingTo(
            "check that selecting academic type shows the proper general and confidential comments in the record",
        );
        await createPastoralRecord.clickTypeDropdown();
        await createPastoralRecord.selectType("1");
        await createPastoralRecord.verifyEntryTip(tipTemplateAcademic);
        await createPastoralRecord.clickSave();
        const recordId = (await db.grabFromDatabase("pastoral", "id", {
            deleted_at: null,
            "heading like": pastoralTitle,
        })) as number;
        await pastoralProfile.clickPastoralRecord(studentId, recordId);
        await pastoralProfile.seeInProfileContent("Academic");
        await pastoralProfile.seeInProfileContent("Award/Achievement");
        await pastoralProfile.seeInGeneralPanel(generalTemplateAcademic);
        await pastoralProfile.clickConfidentialPanelLink();
        await pastoralProfile.seeInConfidentialPanel(confidentialTemplateAcademic);
    });

    // checks if the flags set in the management settings are presented in the pastoral profile and class list pages
    test.skip("set and check pastoral care management settings: flag tests", async ({ page, tester, db }) => {
        pastoralRecordSettings = new PastoralExpiry(page, tester);
        common = new Common(page);
        createPastoralRecord = new CreatePastoralRecord(page);
        profilePage = new ProfilePage(tester, page);
        classList = new ClassList(page, tester);
        studentParticipation = new StudentParticipation(tester, page);

        const studentId = 6;
        const pastoralTitle = "Automation Test Pastoral Record Flag and colour";
        const pastoralTitle2 = "Automation Test Pastoral Record Flag without colour";
        const pastoralTitle3 = "Automation Test Pastoral Record no flag";
        const pastoralTitle4 = "Automation Test Pastoral Record to increment flag count";
        // navigate to pastoral care management settings as a pastoral moderator
        tester.isGoingTo("navigate to pastoral care management settings as a pastoral moderator");
        await tester.impersonate("Jack Coles");
        // set up flag settings
        tester.isGoingTo("set up flag settings");
        // selects a flag and colour for award achievement
        await pastoralRecordSettings.gotoAwardAchievement();
        await pastoralRecordSettings.selectFlag("Achievement");
        await pastoralRecordSettings.fillFlagColor();
        await pastoralRecordSettings.clickSave();
        // selects a flag for learning difficulties
        await pastoralRecordSettings.gotoLearningDifficulties();
        await pastoralRecordSettings.selectFlag("Achievement");
        await pastoralRecordSettings.clickSave();
        // selects a colour for attendance
        await pastoralRecordSettings.gotoBullying();
        await pastoralRecordSettings.fillFlagColor();
        await pastoralRecordSettings.clickSave();
        // create a pastoral record and check the flags are available
        tester.isGoingTo("create a pastoral record and check the flags are available");
        await createPastoralRecord.createPastoralRecord(studentId, pastoralTitle, "1", "1");
        await createPastoralRecord.createPastoralRecord(studentId, pastoralTitle2, "1", "2");
        await createPastoralRecord.createPastoralRecord(studentId, pastoralTitle3, "4");
        // check the flags in the profile page, class list and participation plan
        tester.isGoingTo("check the flags in the profile page and class list");
        await profilePage.goto(studentId);
        await expect(profilePage.getFlag(1, 1)).toBeVisible();
        await expect(profilePage.getFlag(1, 1)).toContainText("1");
        await page.goto(`/learning/class/55`);
        await expect(classList.getFlagLabel(6, 1, 1)).toBeVisible();
        await expect(classList.getFlagLabel(6, 1, 1)).toContainText("1");
        tester.isGoingTo("Visit the student participation page for an activity to check the flag is visible");
        await page.goto("/learning/assessments/550/participation");
        await expect(studentParticipation.getFlagLabel(6, 1, 1)).toBeVisible();
        // create another record to check the flag count increments
        await createPastoralRecord.createPastoralRecord(studentId, pastoralTitle4, "1", "1");
        await profilePage.goto(studentId);
        await expect(profilePage.getFlag(1, 1)).toBeVisible();
        await expect(profilePage.getFlag(1, 1)).toContainText("2");
        await page.goto(`/learning/class/55`);
        await expect(classList.getFlagLabel(6, 1, 1)).toContainText("2");
        await page.goto("/learning/assessments/550/participation");
        await expect(studentParticipation.getFlagLabel(6, 1, 1)).toContainText("2");
        // delete a record and check if the flag count decrements
        tester.isGoingTo("delete a record and check if the flag count decrements");
        await pastoralProfile.goto(studentId);
        const recordId = (await db.grabFromDatabase("pastoral", "id", {
            deleted_at: null,
            "heading like": pastoralTitle4,
        })) as number;
        await pastoralProfile.deletePastoralRecord(recordId);
        await profilePage.goto(studentId);
        await expect(profilePage.getFlag(1, 1)).toContainText("1");
        await page.goto(`/learning/class/55`);
        await expect(classList.getFlagLabel(6, 1, 1)).toContainText("1");
        await page.goto("/learning/assessments/550/participation");
        await expect(studentParticipation.getFlagLabel(6, 1, 1)).toContainText("1");
        // via pastoral management settings remove the flag and save - check the flag is no longer presented
        tester.isGoingTo(
            "via pastoral management settings remove the flag and save - check the flag is no longer presented",
        );
        await tester.impersonate("Jack Coles");
        await pastoralRecordSettings.gotoAwardAchievement();
        await page.getByTestId("audiences-modify-pastoral-flag-select").click();
        await page.keyboard.press("ArrowUp");
        await pastoralRecordSettings.clickSave();
        await profilePage.goto(studentId);
        await expect(profilePage.getFlag(1, 1)).not.toBeVisible();
        await page.goto(`/learning/class/55`);
        await expect(classList.getFlagLabel(6, 1, 1)).not.toBeVisible();
        await page.goto("/learning/assessments/550/participation");
        await expect(studentParticipation.getFlagLabel(6, 1, 1)).not.toBeVisible();
    });

    /**
     * Soft deleting data to not impact the tests
     */
    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does not impact next suite of tests",
        );
        await db.updateInDatabase(
            "pastoral",
            { deleted_at: "2022-01-15 11:22:34" },
            { "heading like": "Automation Test%", deleted_at: null },
        );
        await db.updateInDatabase("pastoral_audience", { deleted_at: "2022-01-15 11:22:34" }, { deleted_at: null });
        await db.updateInDatabase("lu_pastoral_type", { visibility_locked: 0, color: null }, { name: "Bullying" });
        await db.updateInDatabase(
            "lu_pastoral_subtype",
            { sentiment: "neutral", template: null, template_private: null, entry_tip: null, icon: null, color: null },
            { name: "Award/Achievement" },
        );
        await db.updateInDatabase("lu_pastoral_subtype", { icon: null }, { name: "Learning Difficulties" });
        await db.updateInDatabase(
            "lu_pastoral_type",
            { template: null, template_private: null, entry_tip: null },
            { name: "Attendance" },
        );
    });
});

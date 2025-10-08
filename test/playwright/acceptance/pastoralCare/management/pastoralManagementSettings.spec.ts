import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import PastoralProfile from "@schoolboxTest/playwright/support/page/pastoralCare/PastoralProfile";
import CreatePastoralRecord from "@schoolboxTest/playwright/support/page/pastoralCare/CreatePastoralRecord";
import PastoralExpiry from "@schoolboxTest/playwright/support/page/pastoralCare/ManageRecordSettings";
import Common from "@schoolboxTest/playwright/support/page/Common";

/**
 * navigate to pastoral care management settings
 * set tup some record visibility settings for 2 types
 * lock it for one of them
 * check the non pastoral moderator cannot change the locked visibility
 * check the pastoral moderator can change the locked visibility
 */

test.describe("Pastoral Care - Management Settings", () => {
    let pastoralProfile: PastoralProfile;
    let createPastoralRecord: CreatePastoralRecord;
    let pastoralRecordSettings: PastoralExpiry;
    let common: Common;

    test.beforeEach(async ({ page, tester }) => {
        pastoralProfile = new PastoralProfile(page);
        createPastoralRecord = new CreatePastoralRecord(page);
        // login as admin
        await tester.loginAsAdmin();
    });

    test("set and check pastoral care management settings", async ({ page, tester, db }) => {
        pastoralRecordSettings = new PastoralExpiry(page);
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

    /**
     * Soft deleting data to not impact the tests
     */
    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does impact next suite of tests",
        );
        await db.updateInDatabase(
            "pastoral",
            { deleted_at: "2022-01-15 11:22:34" },
            { "heading like": "Automation Test%", deleted_at: null },
        );
        await db.updateInDatabase("pastoral_audience", { deleted_at: "2022-01-15 11:22:34" }, { deleted_at: null });
        await db.updateInDatabase("lu_pastoral_type", { visibility_locked: 0 }, { name: "Bullying" });
    });
});

/**
 * Creates pastoral action settings for a subtype and a type without a subtype,
 * creates records for Jana Reed, verifies default actions are presented, checks
 * action visibility and completion behaviour, and confirms a pastoral comment
 * notification is sent to the record creator.
 *
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. Execute DOCKER=1 npx playwright test pastoralActions.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Common from "@schoolboxTest/playwright/support/page/Common";
import CreatePastoralRecord from "@schoolboxTest/playwright/support/page/pastoralCare/CreatePastoralRecord";
import ManageRecordSettings from "@schoolboxTest/playwright/support/page/pastoralCare/ManageRecordSettings";
import PastoralProfile from "@schoolboxTest/playwright/support/page/pastoralCare/PastoralProfile";
import PastoralRecord from "@schoolboxTest/playwright/support/page/pastoralCare/PastoralRecord";

const SUBTYPE_AVAILABLE_ACTION = `Automation Action Optional`;
const SUBTYPE_DEFAULT_ACTION = `Automation Action Default`;
const ATTENDANCE_DEFAULT_ACTION = `Automation Attendance Action`;
const SUBTYPE_RECORD_TITLE = `Automation Pastoral Actions Subtype`;
const ATTENDANCE_RECORD_TITLE = `Automation Pastoral Actions Attendance`;
const RECORD_COMMENT = `Automation pastoral action comment`;
const COMMENT_NOTIFICATION = "A pastoral care comment has been created";
const STUDENT_ID = Common.USER_ID_JANAREED;

test.describe.serial("Pastoral Actions", () => {
    let previousPastoralActionEnabled = 0;
    let subtypeAvailableActionId = 0;
    let subtypeDefaultActionId = 0;
    let attendanceDefaultActionId = 0;

    test.beforeAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("enable pastoral actions and insert dedicated automation actions");
        previousPastoralActionEnabled =
            Number((await db.grabFromDatabase("config", "value", { name: "pastoral_action_enable" })) ?? 0) || 0;

        await db.updateInDatabase("config", { value: 1 }, { name: "pastoral_action_enable" });

        const createdAt = new Date().toISOString().slice(0, 19).replace("T", " ");
        const baseSequence = (await db.countInDatabase("lu_pastoral_action")) + 1000;

        subtypeAvailableActionId = await db.haveInDatabase("lu_pastoral_action", {
            name: SUBTYPE_AVAILABLE_ACTION,
            external_code: null,
            sequence: baseSequence,
            created_at: createdAt,
            updated_at: createdAt,
            deleted_at: null,
        });
        subtypeDefaultActionId = await db.haveInDatabase("lu_pastoral_action", {
            name: SUBTYPE_DEFAULT_ACTION,
            external_code: null,
            sequence: baseSequence + 1,
            created_at: createdAt,
            updated_at: createdAt,
            deleted_at: null,
        });
        attendanceDefaultActionId = await db.haveInDatabase("lu_pastoral_action", {
            name: ATTENDANCE_DEFAULT_ACTION,
            external_code: null,
            sequence: baseSequence + 2,
            created_at: createdAt,
            updated_at: createdAt,
            deleted_at: null,
        });
    });

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("configure and verify pastoral actions are presented in the pastoral record", async ({ tester, page, db }) => {
        const manageRecordSettings = new ManageRecordSettings(page, tester);
        const createPastoralRecord = new CreatePastoralRecord(page);

        tester.isGoingTo(
            "clear notifications, comment on the record, and verify the record creator receives a notification",
        );
        tester.isGoingTo("as Jack Coles, add available and default actions to Award/Achievement");
        await tester.impersonate("Jack Coles");
        await manageRecordSettings.gotoAwardAchievement();
        await manageRecordSettings.addAvailableActions([SUBTYPE_AVAILABLE_ACTION, SUBTYPE_DEFAULT_ACTION]);
        await manageRecordSettings.addDefaultActions([SUBTYPE_DEFAULT_ACTION]);
        await manageRecordSettings.clickSave();
        await page.reload();
        await manageRecordSettings.expectAvailableActions([SUBTYPE_AVAILABLE_ACTION, SUBTYPE_DEFAULT_ACTION]);
        await manageRecordSettings.expectDefaultActions([SUBTYPE_DEFAULT_ACTION]);

        tester.isGoingTo("as Jack Coles, add a default action to Attendance which has no subtype");
        await manageRecordSettings.gotoAttendance();
        await manageRecordSettings.addAvailableActions([ATTENDANCE_DEFAULT_ACTION]);
        await manageRecordSettings.addDefaultActions([ATTENDANCE_DEFAULT_ACTION]);
        await manageRecordSettings.clickSave();
        await page.reload();
        await manageRecordSettings.expectAvailableActions([ATTENDANCE_DEFAULT_ACTION]);
        await manageRecordSettings.expectDefaultActions([ATTENDANCE_DEFAULT_ACTION]);

        tester.isGoingTo("create a Jana Reed subtype record and verify only the default subtype action is preselected");
        await tester.loginAsAdmin();
        await createPastoralRecord.goto(STUDENT_ID);
        await createPastoralRecord.typeTitle(SUBTYPE_RECORD_TITLE);
        await createPastoralRecord.selectType("1");
        await createPastoralRecord.selectSubType("1");
        await createPastoralRecord.expectSelectedActions([SUBTYPE_DEFAULT_ACTION], [SUBTYPE_AVAILABLE_ACTION]);
        await createPastoralRecord.clickVisibilityLink("Role");
        await createPastoralRecord.clickPastoralRole("Teacher");
        await createPastoralRecord.clickPastoralRole("Parent");
        await tester.common.closeModal();
        await createPastoralRecord.clickSave();

        const subtypeRecordId = Number(
            (await db.grabFromDatabase("pastoral", "id", {
                deleted_at: null,
                heading: SUBTYPE_RECORD_TITLE,
            })) ?? 0,
        );
        expect(subtypeRecordId).toBeGreaterThan(0);

        tester.isGoingTo("create a Jana Reed attendance record and verify the default action is preselected");
        await createPastoralRecord.goto(STUDENT_ID);
        await createPastoralRecord.typeTitle(ATTENDANCE_RECORD_TITLE);
        await createPastoralRecord.selectType("2");
        await createPastoralRecord.expectSelectedActions([ATTENDANCE_DEFAULT_ACTION]);
        await createPastoralRecord.clickSave();

        const attendanceRecordId = Number(
            (await db.grabFromDatabase("pastoral", "id", {
                deleted_at: null,
                heading: ATTENDANCE_RECORD_TITLE,
            })) ?? 0,
        );
        expect(attendanceRecordId).toBeGreaterThan(0);
    });

    test("verify pastoral action visibility in the pastoral record and completion behaviour", async ({
        tester,
        page,
        db,
    }) => {
        const pastoralProfile = new PastoralProfile(page, tester);
        const pastoralRecord = new PastoralRecord(page, tester);
        const subtypeRecordId = Number(
            (await db.grabFromDatabase("pastoral", "id", {
                deleted_at: null,
                heading: SUBTYPE_RECORD_TITLE,
            })) ?? 0,
        );

        tester.isGoingTo("as Jack Coles, verify a pastoral user can complete an action and see who completed it");
        await tester.impersonate("Jack Coles");
        await pastoralProfile.goto(STUDENT_ID);
        await pastoralProfile.clickPastoralRecord(STUDENT_ID, subtypeRecordId);
        await expect(pastoralRecord.getActionsList()).toBeVisible();
        await expect(pastoralRecord.getActionCheckbox(subtypeDefaultActionId)).not.toBeChecked();
        await pastoralRecord.getActionLabel(subtypeDefaultActionId).click();
        await expect(pastoralRecord.getActionCheckbox(subtypeDefaultActionId)).toBeChecked();
        await expect(pastoralRecord.getActionCompletedMeta(subtypeDefaultActionId)).toContainText("Completed");
        await expect(pastoralRecord.getActionCompletedByLink(subtypeDefaultActionId)).toHaveAttribute(
            "title",
            /Jack Coles/,
        );
        await expect(pastoralRecord.getActionCompletedByLink(subtypeDefaultActionId)).toHaveAttribute(
            "href",
            `/search/user/${Common.USER_ID_JACKCOLES}`,
        );
        await pastoralRecord.getActionCompletedByLink(subtypeDefaultActionId).click();
        await expect(page).toHaveURL(new RegExp(`/search/user/${Common.USER_ID_JACKCOLES}$`));

        tester.isGoingTo("as Rebecca White, verify a read pastoral user can uncheck and re-check the action");
        await tester.impersonate("Rebecca White");
        await pastoralProfile.goto(STUDENT_ID);
        await pastoralProfile.clickPastoralRecord(STUDENT_ID, subtypeRecordId);
        await expect(pastoralRecord.getActionsList()).toBeVisible();
        await pastoralRecord.getActionLabel(subtypeDefaultActionId).click();
        await expect(pastoralRecord.getActionCheckbox(subtypeDefaultActionId)).not.toBeChecked();
        await expect(pastoralRecord.getActionStatus(subtypeDefaultActionId)).toHaveCount(0);
        await pastoralRecord.getActionLabel(subtypeDefaultActionId).click();
        await expect(pastoralRecord.getActionCheckbox(subtypeDefaultActionId)).toBeChecked();
        await expect(pastoralRecord.getActionCompletedByLink(subtypeDefaultActionId)).toHaveAttribute(
            "title",
            /Rebecca White/,
        );

        tester.isGoingTo("as Jenni Reed, verify parents cannot view pastoral actions");
        await tester.impersonateViaUserProfilePage("Ms Jenni Reed");
        await pastoralProfile.goto(STUDENT_ID);
        await pastoralProfile.clickPastoralRecord(STUDENT_ID, subtypeRecordId);
        await expect(pastoralRecord.getActionsList()).toHaveCount(0);
    });

    test("verify a notification is sent when a comment is added to a pastoral record", async ({ tester, page, db }) => {
        tester.isGoingTo(
            "clear notifications, comment on the record, and verify the teacher audience receives a notification",
        );
        await tester.runShellCommand(`php /usr/share/schoolbox/test/socket/destroy-notification-redis-data.php -vvv`);
        const pastoralProfile = new PastoralProfile(page, tester);
        const subtypeRecordId = Number(
            (await db.grabFromDatabase("pastoral", "id", {
                deleted_at: null,
                heading: SUBTYPE_RECORD_TITLE,
            })) ?? 0,
        );

        await tester.loginAsAdmin();
        await pastoralProfile.goto(STUDENT_ID);
        await pastoralProfile.clickPastoralRecord(STUDENT_ID, subtypeRecordId);
        await tester.common.clickAddComment();
        await tester.fillCKEditorById("comment-public", RECORD_COMMENT);
        await pastoralProfile.clickPostComment();
        await expect(page.locator("#pastoral-record-comments-container-public")).toContainText(RECORD_COMMENT);

        await tester.impersonate("Jack Coles");
        await tester.common.assertNotifExists(COMMENT_NOTIFICATION);
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("clean up pastoral action configuration and restore the pastoral action toggle");
        await db.dontHaveInDatabase("pastoral_type_action", {
            action_id: [subtypeAvailableActionId, subtypeDefaultActionId, attendanceDefaultActionId],
        });

        await db.updateInDatabase(
            "config",
            { value: previousPastoralActionEnabled },
            { name: "pastoral_action_enable" },
        );
        await db.updateInDatabase("pastoral", { deleted_at: "2022-01-15 11:22:34" }, [
            { heading: SUBTYPE_RECORD_TITLE, deleted_at: null },
            { heading: ATTENDANCE_RECORD_TITLE, deleted_at: null },
        ]);
        await db.dontHaveInDatabase("pastoral_record_action", {
            action_id: [subtypeAvailableActionId, subtypeDefaultActionId, attendanceDefaultActionId],
        });
        await db.dontHaveInDatabase("lu_pastoral_action", {
            id: [subtypeAvailableActionId, subtypeDefaultActionId, attendanceDefaultActionId],
        });
    });
});

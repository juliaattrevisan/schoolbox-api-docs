import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";
import PastoralProfile from "@schoolboxTest/playwright/support/page/pastoralCare/PastoralProfile";
import CreatePastoralRecord from "@schoolboxTest/playwright/support/page/pastoralCare/CreatePastoralRecord";

/**
 * creates a pastoral record, check the pastoral record details, comments it,
 * deletes the comment, modify the record, delete the record
 *
 * Steps:
 * 1. Provision sales data
 * 2. Execute npx playwright test PastoralRecords.spec.ts
 *
 */
test.describe("Pastoral Records", () => {
    /**
     * Preparations
     *
     * get logged in as admin
     */
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    /**
     * Deleting data when the test is about to start so it does not get impacted by other tests already executed
     * @param baseTester
     * @param db
     */
    test.beforeAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update delete_at for the pastoral records so it does not impact the tests");
        await db.updateInDatabase(
            "pastoral",
            { deleted_at: "2022-01-15 11:22:34" },
            { heading: "Automation Test Pastoral Record%" },
        );
        await db.updateInDatabase(
            "pastoral",
            { deleted_at: "2022-01-15 11:22:34" },
            { heading: "Automation Test View Pastoral Record%" },
        );
        await db.updateInDatabase(
            "pastoral",
            { deleted_at: "2022-01-15 11:22:34" },
            { heading: "This is an automation pastoral record updated%" },
        );
        await db.updateInDatabase("config", { value: 1 }, { name: "pastoral_student_access" });
    });

    /**
     *
     * navigate to the student pastoral care page
     * create a pastoral record for the student
     * ensure the record is created with the proper details
     */
    test("Create Pastoral Record", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const pastoralProfile = new PastoralProfile(tester, page);
        const createPastoralRecord = new CreatePastoralRecord(tester, page);
        const studentId = "25";
        const pastoralTitle = "Automation Test Pastoral Record";
        const pastoralDescription = "This is an automation pastoral record";
        tester.isGoingTo("navigate to the student profile page");
        await profilePage.goto(studentId);
        tester.isGoingTo("click the pastoral care button");
        await profilePage.clickButton("pastoral");
        tester.isGoingTo("click create pastoral record and create a pastoral record");
        await pastoralProfile.clickCreatePastoralRecord();
        await createPastoralRecord.typeTitle(pastoralTitle);
        await createPastoralRecord.clickTypeDropdown();
        await createPastoralRecord.selectTypeSubType("1");
        const body = page.locator(`#body_public`);
        await tester.fillCKEditorByLocator(body, pastoralDescription);
        await createPastoralRecord.clickVisibilityLink("Role");
        await createPastoralRecord.clickPastoralRole("Administration");
        await tester.common.closeModal();
        await createPastoralRecord.clickSave();
        tester.isGoingTo("ensure the record is presented under pastoral records");
        await pastoralProfile.goto(studentId);
        await expect(page.locator("#pastoralRecords")).toContainText(pastoralTitle);
    });

    /**
     * navigate to the student pastoral care page
     * comment a pastoral record for the student
     * ensure the comment is created with the proper details
     * delete the comment
     * ensure the comment is deleted
     */

    test("Comment Pastoral Record", async ({ tester, page, db }) => {
        const pastoralProfile = new PastoralProfile(tester, page);
        const studentId = "25";
        const pastoralComment = "This is an automation pastoral comment";
        tester.isGoingTo("navigate to the student pastoral profile page");
        await pastoralProfile.goto(studentId);
        tester.isGoingTo("click the pastoral record");
        const recordId = (await db.grabFromDatabase("pastoral", "id", {
            deleted_at: null,
            "heading like": "Automation Test Pastoral Record%",
        })) as number;
        await pastoralProfile.clickPastoralRecord(studentId, recordId);
        await tester.common.clickAddComment();
        await tester.fillCKEditorById("comment-public", pastoralComment);
        await pastoralProfile.clickPostComment();
        tester.isGoingTo("check the comment is presented");
        await expect(page.locator("#pastoral-record-comments-container-public")).toContainText(pastoralComment);
        tester.isGoingTo("delete the pastoral comment");
        await pastoralProfile.clickDeleteCommentIcon();
        await tester.common.clickModalDeleteButton();
        await expect(page.locator("#pastoral-record-comments-container-public")).not.toContainText(pastoralComment);
    });

    /**
     * navigate to the student pastoral care page
     * modify a pastoral record for the student
     * ensure the record is updated with the proper details
     */

    test("Modify Pastoral Record", async ({ tester, page, db }) => {
        const pastoralProfile = new PastoralProfile(tester, page);
        const createPastoralRecord = new CreatePastoralRecord(tester, page);
        const studentId = "25";
        const pastoralTitle = "This is an automation pastoral record updated";
        tester.isGoingTo("navigate to the pastoral record and click the modify button");
        await pastoralProfile.goto(studentId);
        const recordId = (await db.grabFromDatabase("pastoral", "id", {
            deleted_at: null,
            "heading like": "Automation Test Pastoral Record%",
        })) as number;
        await pastoralProfile.clickPastoralRecord(studentId, recordId);
        await tester.common.clickIconModifyPage();
        tester.isGoingTo("get the pastoral record updated");
        await createPastoralRecord.typeTitle(pastoralTitle);
        await createPastoralRecord.clickTypeDropdown();
        await createPastoralRecord.selectTypeSubType("2");
        await createPastoralRecord.clickSave();
        tester.isGoingTo("ensure the record are properly updated");
        await pastoralProfile.goto(studentId);
        await expect(page.locator("#pastoralRecords")).toContainText(pastoralTitle);
        await pastoralProfile.clickPastoralRecord(studentId, recordId);
        await expect(page.locator("#content")).toContainText("Attendance");
    });

    /**
     * navigate to the student pastoral care page
     * delete a pastoral record for the student
     * ensure the record is no longer presented
     */
    test("Delete Pastoral Record", async ({ tester, page, db }) => {
        const pastoralProfile = new PastoralProfile(tester, page);
        const studentId = "25";
        tester.isGoingTo("navigate to the pastoral profile page and delete a pastoral record");
        await pastoralProfile.goto(studentId);
        const pastoralTitle = "This is an automation pastoral record updated";
        const recordId = (await db.grabFromDatabase("pastoral", "id", {
            deleted_at: null,
            "heading like": pastoralTitle,
        })) as number;
        await pastoralProfile.clickDeleteIcon(recordId);
        await tester.common.clickConfirmButton();
        tester.isGoingTo("ensure the record is no longer presented");
        await page.waitForTimeout(2000);
        await expect(page.locator("#pastoralRecords")).not.toContainText(pastoralTitle);
    });

    /**
     * navigate to the student pastoral care page
     * create a pastoral record for the student
     * ensure the record details is properly presented in the pastoral record page
     */

    test("View Pastoral Record", async ({ tester, page, db }) => {
        const profilePage = new ProfilePage(tester, page);
        const pastoralProfile = new PastoralProfile(tester, page);
        const createPastoralRecord = new CreatePastoralRecord(tester, page);
        const studentId = "25";
        const pastoralTitle = "Automation Test View Pastoral Record";
        const pastoralDescription = "This is an automation pastoral record";
        tester.isGoingTo("navigate to the student profile page");
        await profilePage.goto(studentId);
        tester.isGoingTo("click the pastoral care button");
        await profilePage.clickButton("pastoral");
        tester.isGoingTo("click create pastoral record and create a pastoral record  visible to parents and students");
        await pastoralProfile.clickCreatePastoralRecord();
        await createPastoralRecord.typeTitle(pastoralTitle);
        await createPastoralRecord.clickTypeDropdown();
        await createPastoralRecord.selectTypeSubType("1");
        const body = page.locator(`#body_public`);
        await tester.fillCKEditorByLocator(body, pastoralDescription);
        await createPastoralRecord.clickVisibilityLink("Role");
        await createPastoralRecord.clickPastoralRole("Student");
        await createPastoralRecord.clickPastoralRole("Parent");
        await tester.common.closeModal();
        await createPastoralRecord.clickSave();
        tester.isGoingTo("ensure the record is presented under pastoral records");
        await pastoralProfile.goto(studentId);
        await expect(page.locator("#pastoralRecords")).toContainText(pastoralTitle);
        tester.isGoingTo("as a parent check the pastoral care records details");
        await tester.impersonateViaModifyUserPage("Ms Jenni Reed");
        await pastoralProfile.goto(studentId);
        const recordId = (await db.grabFromDatabase("pastoral", "id", {
            deleted_at: null,
            "heading like": "Automation Test View Pastoral Record%",
        })) as number;
        await pastoralProfile.clickPastoralRecord(studentId, recordId);
        await expect(page.locator("#content")).toContainText("Automation Test View Pastoral Record");
        await expect(page.locator("#content")).toContainText("Academic");
        await expect(page.locator("#content")).toContainText("Award/Achievement");
        tester.isGoingTo("as a student check the pastoral care records details");
        await tester.impersonateViaModifyUserPage("Ms Jana Reed");
        await pastoralProfile.goto(studentId);
        await pastoralProfile.clickPastoralRecord(studentId, recordId);
        await expect(page.locator("#content")).toContainText("Automation Test View Pastoral Record");
        await expect(page.locator("#content")).toContainText("Academic");
        await expect(page.locator("#content")).toContainText("Award/Achievement");
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
            { heading: "Automation Test Pastoral Record%" },
        );
        await db.updateInDatabase(
            "pastoral",
            { deleted_at: "2022-01-15 11:22:34" },
            { heading: "Automation Test View Pastoral Record%" },
        );
        await db.updateInDatabase(
            "pastoral",
            { deleted_at: "2022-01-15 11:22:34" },
            { heading: "This is an automation pastoral record updated%" },
        );
        await db.updateInDatabase("config", { value: 0 }, { name: "pastoral_student_access" });
    });
});

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import ProfileEditor from "@schoolboxTest/playwright/support/page/admin/lms/LessonPlanTemplates/userManagement/ProfileEditor";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";

/**
 * This test checks the profile editor
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. npx playwright test profileEditor.spec.ts
 */

test.describe("Profile Editor", () => {
    test.describe.configure({ mode: "serial" });
    test.beforeEach(async ({ tester }) => {
        await tester.logout();
        await tester.loginAsAdmin();
    });

    /**
     * on profile-editor:student page clear the field name
     * navigate to the student profile page
     * Ensure the student name is presented
     */
    test("Clear Student Name Display Options", async ({ tester, page }) => {
        const profileEditor = new ProfileEditor(tester, page);
        const profilePage = new ProfilePage(tester, page);

        tester.isGoingTo("Navigate to the student profile editor page");
        await profileEditor.goto("student");
        tester.isGoingTo("Clear the field name");
        await profileEditor.clearFieldName();
        tester.isGoingTo("Click the update button");
        await profileEditor.clickUpdateButton();
        tester.isGoingTo("Navigate to the student profile page");
        await profilePage.goto("25");
        tester.isGoingTo("Ensure the student name is presented");
        await profileEditor.checkFieldNameIsPresented("Ms Jana Reed");
    });

    /**
     * profile-editor:staff page set up the field name with {firstname} {lastname} Test
     * navigate to the staff profile page
     * Ensure the staff first and last names and Test are presented
     */
    test("Set Staff Name Display Options", async ({ tester, page }) => {
        const profileEditor = new ProfileEditor(tester, page);
        const profilePage = new ProfilePage(tester, page);

        tester.isGoingTo("Navigate to the staff profile editor page");
        await profileEditor.goto("staff");
        tester.isGoingTo("Set up the field name with {firstname} {lastname} Test");
        await profileEditor.clearFieldName();
        await profileEditor.typeFieldName("{firstname} {lastname} Test");

        tester.isGoingTo("Click the update button");
        await profileEditor.clickUpdateButton();
        await page.waitForTimeout(1000);
        tester.isGoingTo("Navigate to the staff profile page");
        await profilePage.goto("22");
        await page.waitForTimeout(3000);
        tester.isGoingTo("Ensure the staff first and last names and Test are presented");
        await profileEditor.checkFieldNameIsPresented("Jack Coles Test");
    });

    /**
     * on profile-editor: parent page set up the field name with {preferredname} {lastname}
     * navigate to the parent profile page
     * Ensure the parent preferred name and last name are presented
     */
    test("Set Parent Name Display Options", async ({ tester, page }) => {
        const profileEditor = new ProfileEditor(tester, page);
        const profilePage = new ProfilePage(tester, page);

        tester.isGoingTo("Navigate to the parent profile editor page");
        await profileEditor.goto("parent");
        tester.isGoingTo("Set up the field name with {preferredname} {lastname}");
        await profileEditor.clearFieldName();
        await profileEditor.typeFieldName("{preferredname} {lastname}");
        tester.isGoingTo("Click the update button");
        await profileEditor.clickUpdateButton();
        tester.isGoingTo("Navigate to the parent profile page");
        await profilePage.goto("27");
        tester.isGoingTo("Ensure the parent preferred name and last name are presented");
        await profileEditor.checkFieldNameIsPresented("Jenni Reed");
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update the name_format_staff to default value");
        await db.updateInDatabase("config", { value: "{firstname} {lastname}" }, { name: "name_format_staff" });
    });
});

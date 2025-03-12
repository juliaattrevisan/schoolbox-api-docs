import { test } from "@schoolboxTest/playwright/support/integrations";
import ProfileEditor from "@schoolboxTest/playwright/support/page/admin/userManagement/ProfileEditor";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";
import Common from "@schoolboxTest/playwright/support/page/Common";
import SearchPage from "@schoolboxTest/playwright/support/page/search/SearchPage";

/**
 * This test checks the profile editor
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. npx playwright test profileEditor.spec.ts
 */

test.describe("Profile Editor", () => {
    test.describe.configure({ mode: "serial" });

    // Run before each test to ensure login and homepage navigation
    test.beforeEach(async ({ tester }) => {
        await tester.logout();
        await tester.loginAsAdmin();
    });

    test("Verify fields on staff profile pages", async ({ tester, page }) => {
        const profileEditor = new ProfileEditor(tester, page);
        const profilePage = new ProfilePage(tester, page);
        const searchPage = new SearchPage(page);
        const teacher = Common.USER_JACKCOLES;
        tester.isGoingTo("Navigate to the staff profile editor page");
        await profileEditor.goto("staff");
        tester.isGoingTo("Add Role field to staff profile editor page");
        await profileEditor.clickFieldsTab("Internal Fields");
        await profileEditor.clickAvailableField("staff", "role");
        tester.isGoingTo("Click on the cog of role field");
        await profileEditor.clickFieldCog("staff", "role");
        tester.isGoingTo("Click on the visibility checkbox of role field");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "self");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "student");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "parent");
        tester.isGoingTo("Click on the availability checkbox of role field");
        await profileEditor.clickFieldCheckbox("staff", "role", "availability", "1");
        await profileEditor.clickFieldCheckbox("staff", "role", "availability", "2");
        await profileEditor.clickFieldUpdateButton("staff", "role");
        await profileEditor.goto("parent");
        tester.isGoingTo("Add campus field to parent profile editor page");
        await profileEditor.clickFieldsTab("External Fields");
        await profileEditor.clickAvailableField("parent", "campusCollection");
        tester.isGoingTo("Click on the cog of campus field");
        await profileEditor.clickFieldCog("parent", "campusCollection");
        tester.isGoingTo("Click on the visibility checkbox of campus field");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "visibility", "self");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "visibility", "student");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "visibility", "parent");
        tester.isGoingTo("Click on the availability checkbox of campus field");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "availability", "1");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "availability", "2");
        await profileEditor.clickFieldUpdateButton("parent", "campusCollection");
        tester.isGoingTo("Navigate to the Jack Coles profile page");
        await tester.impersonate(teacher);
        await profilePage.goto("22");
        tester.isGoingTo("Ensure the role field is presented on staff profile page");
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Campus");
        tester.isGoingTo("As Jack coles, Ensure the role & Campus field is presented on Jana Reed profile page");
        await searchPage.goto();
        await searchPage.inputKeyword("Jana Reed");
        await searchPage.clearFilters();
        await searchPage.clickSearchButton();
        tester.isGoingTo("Navigate to the Jana Reed profile page");
        await profilePage.goto("25");
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Campus");
        tester.isGoingTo("As Jack coles, Ensure the role & Campus field is presented on Alison Chan profile page");
        await searchPage.goto();
        await searchPage.inputKeyword("Alison Chan");
        await searchPage.clearFilters();
        await searchPage.clickSearchButton();
        tester.isGoingTo("Navigate to the Alison Chan profile page");
        await profilePage.goto("43");
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Campus");
        tester.isGoingTo("As Jack coles, Ensure the role & Campus field is presented on Jenni profile page");
        await searchPage.goto();
        await searchPage.inputKeyword("Jenni Reed");
        await searchPage.clearFilters();
        await searchPage.clickSearchButton();
        tester.isGoingTo("Navigate to the Jenni profile page");
        await profilePage.goto("27");
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Campus");
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
        await profilePage.goto(25);
        tester.isGoingTo("Ensure the student name is presented");
        await profilePage.checkFieldNameIsPresented("Ms Jana Reed");
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
        await profilePage.goto(22);
        tester.isGoingTo("Ensure the staff first and last names and Test are presented");
        await profilePage.checkFieldNameIsPresented("Jack Coles Test");
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
        await page.waitForTimeout(1000);
        tester.isGoingTo("Navigate to the parent profile page");
        await profilePage.goto(27);
        tester.isGoingTo("Ensure the parent preferred name and last name are presented");
        await profilePage.checkFieldNameIsPresented("Jenni Reed");
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update the name_format_student to default value");
        await db.updateInDatabase(
            "config",
            { value: "{title} {firstname} {lastname}" },
            { "name like": "name_format_student" },
        );
        baseTester.isGoingTo("update the name_format_staff to default value");
        await db.updateInDatabase(
            "config",
            { value: "{title} {firstname} {lastname}" },
            { "name like": "name_format_staff" },
        );
        baseTester.isGoingTo("update the name_format_parent to default value");
        await db.updateInDatabase(
            "config",
            { value: "{title} {firstname} {lastname}" },
            { "name like": "name_format_parent" },
        );
    });
});

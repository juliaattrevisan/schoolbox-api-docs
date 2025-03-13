import { test } from "@schoolboxTest/playwright/support/integrations";
import ProfileEditor from "@schoolboxTest/playwright/support/page/admin/userManagement/ProfileEditor";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";
import Common from "@schoolboxTest/playwright/support/page/Common";

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

    test("Verify fields on profile pages", async ({ tester, page }) => {
        const profileEditor = new ProfileEditor(tester, page);
        const profilePage = new ProfilePage(tester, page);
        const teacher = Common.USER_JACKCOLES;

        // Navigate to the profile editor page
        tester.isGoingTo("Navigate to the profile editor page");
        await profileEditor.goto();

        // Select the "Staff" role type and add the "Role" field
        tester.isGoingTo("Click on the staff role type");
        await profileEditor.clickRoleType("Staff");
        await profileEditor.clickFieldsTab("Internal Fields");
        await profileEditor.clickAvailableField("staff", "role");

        // Open settings (cog) for the "Role" field
        tester.isGoingTo("Click on the cog of role field");
        await profileEditor.clickFieldCog("staff", "role");

        /// Set visibility & availability checkboxes
        tester.isGoingTo("Click on the visibility checkbox of role field");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "self");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "student");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "parent");

        // Set availability for different options
        tester.isGoingTo("Click on the availability checkbox of role field");
        await profileEditor.clickFieldCheckbox("staff", "role", "availability", "1");
        await profileEditor.clickFieldCheckbox("staff", "role", "availability", "2");

        // Save changes for "Role" field
        await profileEditor.clickFieldUpdateButton("staff", "role");

        // Switch to the "Parent" role type
        tester.isGoingTo("Click on the parent role type");
        await page.waitForTimeout(1000); // Small wait to ensure UI updates
        await profileEditor.clickRoleType("Parent");

        // Add "Campus" field to the parent profile editor page
        tester.isGoingTo("Add campus field to parent profile editor page");
        await profileEditor.clickFieldsTab("External Fields");
        await profileEditor.clickAvailableField("parent", "campusCollection");

        // Open settings (cog) for the "Campus" field
        tester.isGoingTo("Click on the cog of campus field");
        await profileEditor.clickFieldCog("parent", "campusCollection");

        // Set visibility & availability checkboxes
        tester.isGoingTo("Click on the visibility checkbox of campus field");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "visibility", "self");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "visibility", "student");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "visibility", "parent");
        tester.isGoingTo("Click on the availability checkbox of campus field");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "availability", "1");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "availability", "2");
        await profileEditor.clickFieldUpdateButton("parent", "campusCollection");

        // Impersonate Jack Coles and navigate to his profile
        tester.isGoingTo("Navigate to the Jack Coles profile page");
        await tester.impersonate(teacher);
        await profilePage.goto("22");

        // Verify the "Role" and "Campus" fields are visible on Jack Coles' profile
        tester.isGoingTo("Ensure the role field is presented on staff profile page");

        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Campus");

        // Search for Jana Reed and verify "Role" and "Campus" fields on her profile
        tester.isGoingTo("As Jack Coles, ensure the Role & Campus fields are presented on Jana Reed's profile page");
        await tester.common.search("Jana Reed");
        tester.isGoingTo("Navigate to the Jana Reed profile page");
        await profilePage.goto("25");
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Campus");

        // Search for Alison Chan and verify "Role" and "Campus" fields on her profile
        tester.isGoingTo("As Jack Coles, ensure the Role & Campus fields are presented on Alison Chan's profile page");
        await tester.common.search("Alison Chan");
        tester.isGoingTo("Navigate to the Alison Chan profile page");
        await profilePage.goto("43");
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Campus");

        // Search for Jenni Reed and verify "Role" and "Campus" fields on her profile
        tester.isGoingTo("As Jack Coles, ensure the Role & Campus fields are presented on Jenni Reed's profile page");
        await tester.common.search("Jeeni Reed");
        tester.isGoingTo("Navigate to the Jenni profile page");
        await profilePage.goto("27");
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Campus");
    });

    test("Verify fields are not visible on profile pages when no roles are selected", async ({ tester, page }) => {
        const profileEditor = new ProfileEditor(tester, page);
        const profilePage = new ProfilePage(tester, page);
        const teacher = Common.USER_JACKCOLES;
        const student = Common.USER_JANAREED;
        const parent = Common.USER_JENNIREED;

        // Navigate to the profile editor page
        tester.isGoingTo("Navigate to the profile editor page");
        await profileEditor.goto();

        // Configure visibility settings for the "Role" field under "Student" role
        tester.isGoingTo("Click on the cog of role field");
        await profileEditor.clickFieldCog("student", "role");
        tester.isGoingTo("Click on the visibility checkbox of role field");
        await profileEditor.clickFieldCheckbox("student", "role", "visibility", "self");
        await profileEditor.clickFieldCheckbox("student", "role", "visibility", "student");
        await profileEditor.clickFieldCheckbox("student", "role", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("student", "role", "visibility", "parent");
        await profileEditor.clickFieldUpdateButton("student", "role");

        // Configure visibility settings for the "Campus" field under "Student" role
        tester.isGoingTo("Click on the visibility checkbox of campus field");
        await profileEditor.scrolltoFind("student", "campusCollection");
        await profileEditor.clickFieldCog("student", "campusCollection");
        await profileEditor.clickFieldCheckbox("student", "campusCollection", "visibility", "self");
        await profileEditor.clickFieldCheckbox("student", "campusCollection", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("student", "campusCollection", "visibility", "parent");
        await profileEditor.clickFieldUpdateButton("student", "campusCollection");

        // Navigate back to the profile editor and switch to "Staff" role type
        tester.isGoingTo("Click on the staff role type");
        await profileEditor.goto();
        await profileEditor.clickRoleType("Staff");

        // Configure visibility settings for the "Role" field under "Staff" role
        tester.isGoingTo("Click on the cog of role field");
        await profileEditor.clickFieldCog("staff", "role");
        tester.isGoingTo("Click on the visibility checkbox of role field");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "self");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "student");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "parent");
        await profileEditor.clickFieldUpdateButton("staff", "role");

        // Configure visibility settings for the "Campus" field under "Staff" role
        tester.isGoingTo("Click on the visibility checkbox of campus field");
        await profileEditor.scrolltoFind("staff", "campusCollection");
        await profileEditor.clickFieldCog("staff", "campusCollection");
        await profileEditor.clickFieldCheckbox("staff", "campusCollection", "visibility", "self");
        await profileEditor.clickFieldCheckbox("staff", "campusCollection", "visibility", "staff");
        await profileEditor.clickFieldUpdateButton("staff", "campusCollection");

        // Switch to "Parent" role type
        tester.isGoingTo("Click on the parent role type");
        await page.waitForTimeout(1000);
        await profileEditor.clickRoleType("Parent");

        // Configure visibility settings for the "Campus" field under "Parent" role
        tester.isGoingTo("Click on the cog of campus field");
        await profileEditor.clickFieldCog("parent", "campusCollection");
        tester.isGoingTo("Click on the visibility checkbox of campus field");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "visibility", "self");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "visibility", "student");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "visibility", "parent");
        await profileEditor.clickFieldUpdateButton("parent", "campusCollection");

        // Configure visibility settings for the "Role" field under "Parent" role
        tester.isGoingTo("Click on the cog of role field");
        await profileEditor.clickFieldCog("parent", "role");
        tester.isGoingTo("Click on the visibility checkbox of role field");
        await profileEditor.clickFieldCheckbox("parent", "role", "visibility", "self");
        await profileEditor.clickFieldCheckbox("parent", "role", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("parent", "role", "visibility", "parent");
        await profileEditor.clickFieldUpdateButton("parent", "role");

        // Impersonate as Jack Coles
        tester.isGoingTo("Navigate to the Jack Coles profile page");
        await tester.impersonate(teacher);

        // Search for Jana Reed and verify "Role" and "Campus" fields are not present on her profile
        tester.isGoingTo("As Jack Coles, ensure the Role & Campus fields are presented on Jana Reed's profile page");
        await tester.common.search("Jana Reed");
        tester.isGoingTo("Navigate to the Jana Reed profile page");
        await profilePage.goto("25");
        await profilePage.checkFieldIsNotVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");

        // Search for Alison Chan and verify "Role" and "Campus" fields are not present on her profile
        tester.isGoingTo("As Jack Coles, ensure the Role & Campus fields are presented on Alison Chan's profile page");
        await tester.common.search("Alison Chan");
        tester.isGoingTo("Navigate to the Alison Chan profile page");
        await profilePage.goto("43");
        await profilePage.checkFieldIsNotVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");

        // Search for Jenni Reed and verify "Role" and "Campus" fields are not present on her profile
        tester.isGoingTo("As Jack Coles, ensure the Role & Campus fields are presented on Jenni Reed's profile page");
        await tester.common.search("Jeeni Reed");
        tester.isGoingTo("Navigate to the Jenni profile page");
        await profilePage.goto("27");
        await profilePage.checkFieldIsNotVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");

        // Impersonate Jana Reed and navigate to his profile
        tester.isGoingTo("Navigate to the Jack Coles profile page");
        await tester.impersonate(student);

        // Search for Jack Coles and verify "Role" and "Campus" fields are not present on her profile
        tester.isGoingTo(
            "As Jana Reed, ensure the Role & Campus fields are not presented on Jack Coles's profile page",
        );
        await tester.common.search("Jack Coles");
        tester.isGoingTo("Navigate to the Jack Coles profile page");
        await profilePage.goto("22");
        await profilePage.checkFieldIsNotVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");

        // Search for Eden Reed and verify "Role" and "Campus" fields are not present on her profile
        tester.isGoingTo("As Jana Reed, ensure the Role & Campus fields are not presented on Eden Reed's profile page");
        await tester.common.search("Eden Reed");
        tester.isGoingTo("Navigate to the Eden Reed profile page");
        await profilePage.goto("6");
        await profilePage.checkFieldIsNotVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");

        // Search for Jenni Reed and verify "Role" and "Campus" fields are not present on her profile
        tester.isGoingTo(
            "As Jana Reed, ensure the Role & Campus fields are not presented on Jenni Reed's profile page",
        );
        await tester.common.search("Jeeni Reed");
        tester.isGoingTo("Navigate to the Jenni profile page");
        await profilePage.goto("27");
        await profilePage.checkFieldIsNotVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");

        // Impersonate Jenni Reed and navigate to his profile
        tester.isGoingTo("Navigate to the Jack Coles profile page");
        await tester.impersonate(parent);

        // Search for Jack Coles and verify "Role" and "Campus" fields are not present on her profile
        tester.isGoingTo(
            "As Jenni Reed, ensure the Role & Campus fields are not presented on Jack Coles's profile page",
        );
        await tester.common.search("Jack Coles");
        tester.isGoingTo("Navigate to the Jack Coles profile page");
        await profilePage.goto("22");
        await profilePage.checkFieldIsNotVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");

        // Search for Jana Reed and verify "Role" and "Campus" fields are not present on her profile
        tester.isGoingTo("As Jenni Reed, ensure the Role & Campus fields are presented on Jana Reed's profile page");
        await tester.common.search("Jana Reed");
        tester.isGoingTo("Navigate to the Jana Reed profile page");
        await profilePage.goto("25");
        await profilePage.checkFieldIsNotVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");
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
        await profileEditor.goto();
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
        await profileEditor.goto();
        await page.waitForTimeout(1000);
        await profileEditor.clickRoleType("Staff");

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
        await profileEditor.goto();
        await profileEditor.clickRoleType("Staff");
        await profileEditor.clickRoleType("Parent");
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

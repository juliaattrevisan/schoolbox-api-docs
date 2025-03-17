import { test, expect } from "@schoolboxTest/playwright/support/integrations";
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

    /**
     * Set Up Field Visibility and Availability
     * 1. Update Rebecca White’s email in the database (ID: 24).
     * 2. Student Tab:
     *    - Student ID: "Appears On" = Secondary “Visible for”= All
     *    - Year Level: "Appears On" = All  “Visible for”= Satff
     *    - Campus: "Appears On" = All “Visible for”= None
     * 3. Staff Tab:
     *    - Add Role field
     *    - Role: "Appears On" = Secondary “Visible for”= All
     *    - Email: "Appears On" = Secondary “Visible for”= Self
     *    - Campus: "Appears On" = None “Visible for”= None
     * 4. Parent Tab:
     *    - Campus: "Appears On" = All “Visible for”= None
     *    - Role: "Appears On" = All “Visible for”= All
     */

    test("Setting up fields on profile pages", async ({ tester, page, db }) => {
        // Update alt_email for Rebecca White
        tester.isGoingTo("update alt_email for Rebecca White so data is consistent for this test run");
        await db.updateInDatabase("user", { alt_email: "test@schoolbox.com.au" }, { id: 24 });

        const profileEditor = new ProfileEditor(tester, page);

        // Navigate to the profile editor page
        tester.isGoingTo("Navigate to the profile editor page");
        await profileEditor.goto();

        // Part A: Set up fields on staff profile page
        tester.isGoingTo("Set up fields on Student profile editor page");
        tester.isGoingTo("Click on the student role type");

        // Select the "Student ID" field and open settings
        tester.isGoingTo("Click on the cog of Student ID field");
        await profileEditor.clickFieldCog("student", "externalId");

        // Set visibility & availability checkboxes: Secondary and Student checkboxes
        tester.isGoingTo("Click on the Primary and Student checkboxes for Student ID field");
        await profileEditor.clickFieldCheckbox("student", "externalId", "visibility", "student");
        await profileEditor.clickFieldCheckbox("student", "externalId", "availability", "2");
        await profileEditor.clickFieldUpdateButton("student", "externalId");

        // Select the "Year Level" field and open settings
        tester.isGoingTo("Click on the cog of Year Level field");
        await profileEditor.clickFieldCog("student", "yearLevel");

        // Set visibility & availability checkboxes: Self and Parent checkboxes
        tester.isGoingTo("Click on the Self and Parent checkboxes for Year Level field");
        await profileEditor.clickFieldCheckbox("student", "yearLevel", "visibility", "self");
        await profileEditor.clickFieldCheckbox("student", "yearLevel", "visibility", "parent");
        await profileEditor.clickFieldUpdateButton("student", "yearLevel");

        // Select the "Campus" field and open settings

        tester.isGoingTo("Click on the cog of Campus field");
        await profileEditor.clickFieldCog("student", "campusCollection");

        // Set visibility & availability checkboxes: Only all "Appears On" checkboxes
        tester.isGoingTo("Click on the Self, Staff, and Parent checkboxes for Campus field");
        await profileEditor.clickFieldCheckbox("student", "campusCollection", "visibility", "self");
        await profileEditor.clickFieldCheckbox("student", "campusCollection", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("student", "campusCollection", "visibility", "parent");
        await profileEditor.clickFieldUpdateButton("student", "campusCollection");

        // Part B: Set up fields on staff profile page
        tester.isGoingTo("Set up fields on Staff profile editor page");
        tester.isGoingTo("Click on the staff role type");
        await profileEditor.clickRoleType("staff");

        // Select the "Staff" role type and add the "Role" field
        tester.isGoingTo("Adding role field to staff profile editor page");
        await profileEditor.clickFieldsTab("Internal Fields");
        await profileEditor.clickAvailableField("staff", "role");

        // Select the "Role" field and open settings
        tester.isGoingTo("Click on the cog of role field");
        await profileEditor.clickFieldCog("staff", "role");

        /// Set visibility & availability checkboxes: All checkboxes
        tester.isGoingTo("Click on the visibility checkbox of role field");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "self");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "student");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("staff", "role", "visibility", "parent");
        tester.isGoingTo("Click on the availability checkbox of role field");
        await profileEditor.clickFieldCheckbox("staff", "role", "availability", "1");
        await profileEditor.clickFieldCheckbox("staff", "role", "availability", "2");
        await profileEditor.clickFieldUpdateButton("staff", "role");
        await expect(page.getByTestId("toast")).toHaveAttribute("data-test-is-success", "true");
        await expect(page.getByTestId("toast")).not.toBeVisible();

        // Select the "Campus" field and open settings
        tester.isGoingTo("Click on the cog of Campus field");
        await profileEditor.clickFieldCog("staff", "campusCollection");

        // Set visibility checkboxes: All checkboxes
        tester.isGoingTo("Click on the visibility checkbox of Campus field");
        await profileEditor.clickFieldCheckbox("staff", "campusCollection", "visibility", "self");
        await profileEditor.clickFieldCheckbox("staff", "campusCollection", "visibility", "staff");
        await page.waitForTimeout(3000);
        await profileEditor.clickFieldUpdateButton("staff", "campusCollection");
        await expect(page.getByTestId("toast")).toBeVisible();

        // Select the "Email" field and open settings
        tester.isGoingTo("Click on the cog of Email field");
        await profileEditor.clickFieldCog("staff", "email");

        // Set visibility checkboxes: Secondary & Self
        tester.isGoingTo("Click on the visibility checkbox of Email field");
        await profileEditor.clickFieldCheckbox("staff", "email", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("staff", "email", "availability", "2");
        await profileEditor.clickFieldUpdateButton("staff", "email");

        // Part C: Set up fields on Parent profile page
        tester.isGoingTo("Set up fields on Parent profile editor page");
        tester.isGoingTo("Click on the parent role type");
        await profileEditor.clickRoleType("parent");

        // Add "Campus" field and Open settings (cog) for the "Campus" field
        tester.isGoingTo("Add campus field to parent profile editor page");
        await profileEditor.clickFieldsTab("External Fields");
        await profileEditor.clickAvailableField("parent", "campusCollection");
        tester.isGoingTo("Click on the cog of campus field");
        await profileEditor.clickFieldCog("parent", "campusCollection");

        // Set visibility & availability checkboxes: only Appears on checked
        tester.isGoingTo("Click on the availability checkbox of campus field");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "availability", "1");
        await profileEditor.clickFieldCheckbox("parent", "campusCollection", "availability", "2");
        await profileEditor.clickFieldUpdateButton("parent", "campusCollection");

        // Select the "Role" field and open settings
        tester.isGoingTo("Click on the cog of Role field");
        await profileEditor.clickFieldCog("parent", "role");

        // Set visibility checkbox: Student
        tester.isGoingTo("Click on the visibility checkbox of Role field for Student");
        await profileEditor.clickFieldCheckbox("parent", "role", "visibility", "student");
        await profileEditor.clickFieldUpdateButton("parent", "role");
    });

    /**
     * Verify Field Visibility Across Profile Pages
     *
     *  Verify that
     *    - Jack Coles: Jana Reed, Alison Chan, Jenni Reed
     *    - Jana Reed: Jack Coles, Eden Reed, Jenni Reed
     *    - Jenni Reed: Jana Reed, Jack Coles
     */

    test("Verify fields are not visible on profile pages when no roles are selected", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const teacher = Common.USER_JACKCOLES;
        const student = Common.USER_JANAREED;
        const parent = Common.USER_JENNIREED;

        // Impersonate Jack Coles

        await tester.impersonate(teacher);

        // As Jack Coles , Navigating to own profile and verify role, email are presented and campus is not presented
        tester.isGoingTo("Navigate to the Jack Coles profile page");
        await profilePage.goto("22");
        tester.isGoingTo(
            "As Jack Coles, ensure role, email are presented and campus is not presented on his own profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Email");
        await profilePage.checkFieldIsNotVisible("Campus");

        // As Jack Coles , Navigating to Jana Reed and verify role, student id, year level are presented and campus is not presented

        tester.isGoingTo("Navigate to the Jana Reed profile page");
        await profilePage.goto("25");
        tester.isGoingTo(
            "As Jack Coles, ensure role, student id, year level are presented and campus is not presented on Jana Reed's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Student ID");
        await profilePage.checkFieldIsNotVisible("Campus");

        // As Jack Coles, Navigating to Eden Reed and verify role, year level  are presented and campus, student id are not presented

        tester.isGoingTo("Navigate to the Eden Reed profile page");
        await profilePage.goto("6");
        tester.isGoingTo(
            "As Jack coles, ensure role, year level  are presented and campus, student id are not presented on Eden Reed's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Year Level");
        await profilePage.checkFieldIsNotVisible("Campus");
        await profilePage.checkFieldIsNotVisible("Student ID");

        // As Jack Coles, Navigating to Rebecca White and verify role is presented and campus, email are not presented

        tester.isGoingTo("Navigate to Rebecca White profile page");
        await profilePage.goto("24");
        tester.isGoingTo(
            "As Jack Coles, ensure the Role & Campus fields are presented on Rebecca White's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsNotVisible("Email");
        await profilePage.checkFieldIsNotVisible("Campus");

        // As Jack Coles, Navigating to Jenni Reed and verify role is presented and campus is not presented
        tester.isGoingTo("Navigate to the Jenni profile page");
        await profilePage.goto("27");
        tester.isGoingTo("As Jack Coles, ensure the Role & Campus fields are presented on Jenni Reed's profile page");
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");

        // Impersonate as Jana Reed
        await tester.impersonate(student);

        // As Jana Reed , Navigating to own profile and verify role, student Id are presented and  campus, year level are  not presented

        tester.isGoingTo("Jana Reed navigating to her own profile page");
        await profilePage.goto("25");
        tester.isGoingTo(
            "As Jana Reed, ensure role, student Id are presented and campus, year level are  not presented on her own profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Student ID");
        await profilePage.checkFieldIsNotVisible("Campus");
        await profilePage.checkFieldIsNotVisible("Year Level");

        // As Jana Reed , Navigating to Eden Reed profile and verify role is presented and student id, year level and campus  are  not presented

        tester.isGoingTo("Navigate to the Eden Reed profile page");
        await profilePage.goto("6");
        tester.isGoingTo(
            "As Jana Reed, ensure the role is presented and student id, year level and campus  are  not presented on Eden Reed's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsNotVisible("Student ID");
        await profilePage.checkFieldIsNotVisible("Year Level");
        await profilePage.checkFieldIsNotVisible("Campus");

        //  As Jana Reed , Navigating to Jack Coles and verify role is presented and email, Campus fields are not presented

        tester.isGoingTo("Navigate to the Jack Coles profile page");
        await profilePage.goto("22");
        tester.isGoingTo(
            "As Jana Reed, ensure the Role is presented and email, Campus fields are not presented on Jack Coles's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsNotVisible("Email");
        await profilePage.checkFieldIsNotVisible("Campus");

        // As Jana Reed , Navigating to Jenni Reed and verify role is presented and campus field are not presented
        tester.isGoingTo("Navigate to the Jenni profile page");
        await profilePage.goto("27");
        tester.isGoingTo(
            "As Jana Reed, ensure Role is presented and Campus field are not presented on Jenni Reed's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");

        // Impersonate Jenni Reed
        tester.isGoingTo("Impersonating as Jenni Reed");
        await tester.impersonate(parent);

        // As Jenni Reed , Navigating to Jack Coles and verify "Role" is presented and student id, year level and campus fields are  not presented
        tester.isGoingTo("Navigate to the Jack Coles profile page");
        await profilePage.goto("22");
        tester.isGoingTo(
            "As Jenni Reed, ensure Role is presented and student id, year level & campus fields are  not presented on Jack Coles's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");
        await profilePage.checkFieldIsNotVisible("Student ID");
        await profilePage.checkFieldIsNotVisible("Year Level");

        // As Jenni Reed , Navigating to Jana Reed and verify "Role" , "Student id" are presented and "Campus" and  Year Level fields are not presented

        tester.isGoingTo("Navigate to the Jana Reed profile page");
        await profilePage.goto("25");
        tester.isGoingTo(
            "As Jenni Reed, ensure Role, Student id are presented and Campus, Year Level fields are not presented on Jana Reed's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Student ID");
        await profilePage.checkFieldIsNotVisible("Campus");
        await profilePage.checkFieldIsNotVisible("Year Level");

        // As Jenni Reed , Navigating to Eden Reed profile and verify role is presented and campus is not presented

        tester.isGoingTo("Navigate to the Eden Reed profile page");
        await profilePage.goto("6");
        tester.isGoingTo(
            "As Jenni Reed, ensure role is presented and campus is not presented on Eden Reed's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");
    });

    /**
     * on profile-editor:student page clear the field name
     * navigate to the student profile page
     * Ensure the student name is presented
     */
    test("Clear Student Name Display Options: defaults to title firstname lastname", async ({ tester, page }) => {
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
    test("Set Staff Name Display Options: tokens and text are presented in the profile page", async ({
        tester,
        page,
    }) => {
        const profileEditor = new ProfileEditor(tester, page);
        const profilePage = new ProfilePage(tester, page);

        tester.isGoingTo("Navigate to the staff profile editor page");
        await profileEditor.goto();
        await profileEditor.clickRoleType("staff");
        tester.isGoingTo("Set up the field name with {firstname} {lastname} Test");
        await profileEditor.clearFieldName();
        await profileEditor.typeFieldName("{firstname} {lastname} Test");
        tester.isGoingTo("Click the update button");
        await profileEditor.clickUpdateButton();
        tester.isGoingTo("Navigate to the staff profile page");
        await page.waitForTimeout(1000); // Added this timeout to allow sufficient time for the updated names to reflect on the profile screen
        await profilePage.goto(22);
        tester.isGoingTo("Ensure the staff first and last names and Test are presented");
        await profilePage.checkFieldNameIsPresented("Jack Coles Test");
    });

    /**
     * on profile-editor: parent page set up the field name with {preferredname} {lastname}
     * navigate to the parent profile page
     * Ensure the parent preferred name and last name are presented
     */
    test("Set Parent Name Display Options to preferredname: when there is no preferred name defaults to firstname", async ({
        tester,
        page,
    }) => {
        const profileEditor = new ProfileEditor(tester, page);
        const profilePage = new ProfilePage(tester, page);

        tester.isGoingTo("Navigate to the parent profile editor page");
        await profileEditor.goto();
        await profileEditor.clickRoleType("staff");
        await profileEditor.clickRoleType("parent");
        tester.isGoingTo("Set up the field name with {preferredname} {lastname}");
        await profileEditor.clearFieldName();
        await profileEditor.typeFieldName("{preferredname} {lastname}");
        tester.isGoingTo("Click the update button");
        await profileEditor.clickUpdateButton();
        tester.isGoingTo("Navigate to the parent profile page");
        await page.waitForTimeout(1000); // Added this timeout to allow sufficient time for the updated names to reflect on the profile screen
        await profilePage.goto(27);
        tester.isGoingTo("Ensure the parent preferred name and last name are presented");
        await profilePage.checkFieldNameIsPresented("Jenni Reed");
    });

    test("Restore fields on profile Editor", async ({ tester, page }) => {
        const profileEditor = new ProfileEditor(tester, page);

        // Navigate to the profile editor page
        tester.isGoingTo("Navigate to the profile editor page");
        await profileEditor.goto();

        // Part A: Restoring fields on student profile page
        tester.isGoingTo("Restore fields on Student profile editor page");
        tester.isGoingTo("Restore the Student ID field");
        await profileEditor.clickFieldCog("student", "externalId");
        await profileEditor.clickFieldCheckbox("student", "externalId", "visibility", "student");
        await profileEditor.clickFieldCheckbox("student", "externalId", "availability", "2");
        await profileEditor.clickFieldUpdateButton("student", "externalId");
        tester.isGoingTo("Restore the Student: Year Level field");
        await profileEditor.clickFieldCog("student", "yearLevel");
        await profileEditor.clickFieldCheckbox("student", "yearLevel", "visibility", "self");
        await profileEditor.clickFieldCheckbox("student", "yearLevel", "visibility", "parent");
        await profileEditor.clickFieldUpdateButton("student", "yearLevel");
        tester.isGoingTo("Restore the Student: Campus field");
        await profileEditor.clickFieldCog("student", "campusCollection");
        await profileEditor.clickFieldCheckbox("student", "campusCollection", "visibility", "student");
        await profileEditor.clickFieldCheckbox("student", "campusCollection", "availability", "1");
        await profileEditor.clickFieldCheckbox("student", "campusCollection", "availability", "2");
        await profileEditor.clickFieldUpdateButton("student", "campusCollection");

        // Part B: Restoring fields on staff profile editor page
        tester.isGoingTo("Restore fields on Staff profile editor page");
        await profileEditor.clickRoleType("staff");
        tester.isGoingTo("Restore the Staff: email field");
        await profileEditor.clickFieldCog("staff", "email");
        await profileEditor.clickFieldCheckbox("staff", "email", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("staff", "email", "availability", "2");
        await profileEditor.clickFieldUpdateButton("staff", "email");
        tester.isGoingTo("Restore the Staff: Campus field");
        await profileEditor.clickFieldCog("staff", "campusCollection");
        await profileEditor.clickFieldCheckbox("staff", "campusCollection", "visibility", "self");
        await profileEditor.clickFieldCheckbox("staff", "campusCollection", "visibility", "staff");
        await profileEditor.clickFieldUpdateButton("staff", "campusCollection");

        // Part C: Restoring fields on Parent profile Editor page
        tester.isGoingTo("Restore fields on Parent profile editor page");
        await profileEditor.clickRoleType("parent");
        tester.isGoingTo("Restore the Parent: Role field");
        await profileEditor.clickFieldCog("parent", "role");
        await profileEditor.clickFieldCheckbox("parent", "role", "visibility", "student");
        await profileEditor.clickFieldUpdateButton("parent", "role");
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

        baseTester.isGoingTo("reset alt_email for Rebecca White");
        await db.updateInDatabase("user", { alt_email: null }, { id: 24 });

        baseTester.isGoingTo("Remove Role field from Staff tab");
        await db.updateInDatabase("profile_field", { enabled: 0 }, { roletype: "staff", field: "role" });

        baseTester.isGoingTo("Remove Campus field from Parent tab");
        await db.updateInDatabase("profile_field", { enabled: 0 }, { roletype: "parent", field: "campusCollection" });
    });
});

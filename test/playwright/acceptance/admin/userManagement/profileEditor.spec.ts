import { test } from "@schoolboxTest/playwright/support/integrations";
import type { Page } from "@playwright/test";
import Common from "@schoolboxTest/playwright/support/page/Common";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";
import ProfileEditor from "@schoolboxTest/playwright/support/page/admin/userManagement/ProfileEditor";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";

/**
 * Function to set up fields on profile editor page
 * @param tester - The tester
 * @param page - The page
 * @param db - The database helper
 */
async function setupProfileEditorPage(tester: AcceptanceTester, page: Page, db: DatabaseHelper): Promise<void> {
    const profileEditor = new ProfileEditor(tester, page);

    // Update alt_email for Rebecca White
    tester.isGoingTo("update alt_email for Rebecca White so data is consistent for this test run");
    await db.updateInDatabase("user", { alt_email: "test@schoolbox.com.au" }, { id: 24 });

    // Navigate to the profile editor page
    tester.isGoingTo("Navigate to the profile editor page");
    await profileEditor.goto();

    // Part A: Set up fields on student profile page
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

    // PART B: Set up Tabs on student profile page
    tester.isGoingTo("Set up tabs on Student profile editor page");

    // Set up the Goals tab to be visible for only for self
    tester.isGoingTo("Set up the Goals tab to have visible for only for self");
    await profileEditor.clickTabCog("student", "goals");
    await profileEditor.clickTabCheckbox("student", "goals", "visibility", "staff");
    await profileEditor.clickTabCheckbox("student", "goals", "visibility", "parent");
    await profileEditor.clickTabUpdateButton("student", "goals");

    // Set up Achievements tab to be visible for only for parent
    tester.isGoingTo("Set up the Achievements tab to have visible for only for parent");
    await profileEditor.clickTabCog("student", "achievements");
    await profileEditor.clickTabCheckbox("student", "achievements", "visibility", "self");
    await profileEditor.clickTabCheckbox("student", "achievements", "visibility", "staff");
    await profileEditor.clickTabUpdateButton("student", "achievements");

    // Set up Classes tab to be appears on only for secondary
    tester.isGoingTo("Set up the Classes tab to have appears on only for secondary");
    await profileEditor.clickTabCog("student", "classes");
    await profileEditor.clickTabCheckbox("student", "classes", "availability", "2");
    await profileEditor.clickTabUpdateButton("student", "classes");

    // Part C: Set up fields on staff profile page
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

    // Select the "Campus" field and open settings
    tester.isGoingTo("Click on the cog of Campus field");
    await profileEditor.clickFieldCog("staff", "campusCollection");

    // Set visibility checkboxes: All checkboxes
    tester.isGoingTo("Click on the visibility checkbox of Campus field");
    await profileEditor.clickFieldCheckbox("staff", "campusCollection", "visibility", "self");
    await profileEditor.clickFieldCheckbox("staff", "campusCollection", "visibility", "staff");
    await profileEditor.clickFieldUpdateButton("staff", "campusCollection");

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
}

/**
 * As a lot of data is changing in this test (but reversable),
 * the following tables will be backed up at the start and then restored at the end
 * of the test, as to ensure no other test is effected by what happens in here
 */
const TABLES_TO_RESTORE = [
    "profile_button",
    "profile_button_campus_availability",
    "profile_button_campus_visibility",
    "profile_button_role_availability",
    "profile_button_role_visibility",
    "profile_field",
    "profile_field_campus_availability",
    "profile_field_roletype_visibility",
    "profile_tab",
    "profile_tab_campus_availability",
    "profile_tab_roletype_visibility",
];

/**
 * This test checks the profile editor
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. npx playwright test profileEditor.spec.ts
 */
test.describe("Profile Editor", () => {
    test.describe.configure({ mode: "serial" });

    // Make a backup of the tables that will be restored at the end of the test
    test.beforeAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("back up tables to restore at the end of the test");
        await Promise.all(TABLES_TO_RESTORE.map((table) => db.backupTable(table)));
    });

    // Run before each test to ensure login and homepage navigation
    test.beforeEach(async ({ tester }) => {
        await tester.logout();
        await tester.loginAsAdmin();
    });

    /**
     * TEST 1: Verify fields Availability & Visibility across Profile Pages
     * - Jack Coles: Own, Jana Reed, Eden Reed, Jenni Reed, Rebecca white
     * - Jana Reed: Own, Jack Coles, Eden Reed, Jenni Reed
     * - Jenni Reed: Jana Reed, Eden Reed, Jack Coles
     * TEST 2: Verify Tabs Availability & Visibility across Profile Pages
     * - Jack Coles: Jana Reed, Eden Reed
     * - Jana Reed: Own, Eden Reed
     * - Jenni Reed: Jana Reed, Eden Reed
     */
    test("Profile Editor fields and tabs visibility check", async ({ tester, page, db }) => {
        // Call setupProfileEditorPage before running visibility checks
        await setupProfileEditorPage(tester, page, db);

        const profilePage = new ProfilePage(tester, page);

        const teacher = Common.USER_JACKCOLES;
        const student = Common.USER_JANAREED;
        const parent = Common.USER_JENNIREED;

        // Impersonate Jack Coles
        await tester.impersonate(teacher);

        // As Jack Coles , Navigating to own profile and verify role, email are presented and campus is not presented
        tester.isGoingTo("Navigate to the Jack Coles profile page");
        await profilePage.goto(22);
        tester.isGoingTo(
            "As Jack Coles, ensure role, email are presented and campus is not presented on his own profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Email");
        await profilePage.checkFieldIsNotVisible("Campus");

        // As Jack Coles , Navigating to Jana Reed and verify role, student id, year level are presented and campus is not presented
        tester.isGoingTo("Navigate to the Jana Reed profile page");
        await profilePage.goto(25);
        tester.isGoingTo(
            "As Jack Coles, ensure role, student id, year level are presented and campus is not presented on Jana Reed's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Student ID");
        await profilePage.checkFieldIsVisible("Year Level");
        await profilePage.checkFieldIsNotVisible("Campus");

        // As Jack Coles, verify activity feed, classes, groups and contacts tabs are presented & goals and achievement tabs are not presented on Jana Reed's profile page

        tester.isGoingTo(
            "As Jack Coles, ensure activity feed, classes, groups and contacts tabs are presented on Jana Reed's profile page",
        );
        await profilePage.checkTabIsVisible("activity");
        await profilePage.checkTabIsVisible("classes");
        await profilePage.checkTabIsVisible("groups");
        await profilePage.checkTabIsVisible("contacts");
        tester.isGoingTo(
            "As Jack Coles, ensure goals and achievement tabs are not presented on Jana Reed's profile page",
        );
        await profilePage.checkTabIsNotVisible("goals");
        await profilePage.checkTabIsNotVisible("achievements");

        // As Jack Coles, Navigating to Eden Reed and verify role, year level are presented and campus, student id are not presented
        tester.isGoingTo("Navigate to the Eden Reed profile page");
        await profilePage.goto(6);
        tester.isGoingTo(
            "As Jack coles, ensure role, year level are presented and campus, student id are not presented on Eden Reed's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Year Level");
        await profilePage.checkFieldIsNotVisible("Campus");
        await profilePage.checkFieldIsNotVisible("Student ID");

        // As Jack Coles, verify activity feed, groups and contacts tabs are presented & goals, achievement and classes tabs are not presented on Eden Reed's profile page
        tester.isGoingTo(
            "As Jack coles, ensure activity feed, groups and contacts tabs are presented on Eden Reed's profile page",
        );
        await profilePage.checkTabIsVisible("activity");
        await profilePage.checkTabIsVisible("groups");
        await profilePage.checkTabIsVisible("contacts");
        tester.isGoingTo(
            "As Jack coles, ensure goals, achievement and classes tabs are not presented on Eden Reed's profile page",
        );
        await profilePage.checkTabIsNotVisible("goals");
        await profilePage.checkTabIsNotVisible("achievements");
        await profilePage.checkTabIsNotVisible("classes");

        // As Jack Coles, Navigating to Rebecca White and verify role is presented and campus, email are not presented
        tester.isGoingTo("Navigate to Rebecca White profile page");
        await profilePage.goto(24);
        tester.isGoingTo(
            "As Jack Coles, ensure the role is presented and campus , email are not presented on Rebecca White's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsNotVisible("Email");
        await profilePage.checkFieldIsNotVisible("Campus");

        // As Jack Coles, Navigating to Jenni Reed and verify role is presented and campus is not presented
        tester.isGoingTo("Navigate to the Jenni profile page");
        await profilePage.goto(27);
        tester.isGoingTo(
            "As Jack Coles, ensure the role is presented and campus is not presented on Jenni Reed's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");

        // Impersonate as Jana Reed
        await tester.impersonate(student);

        // As Jana Reed , Navigating to own profile and verify role, student Id are presented and campus, year level are not presented
        tester.isGoingTo("Jana Reed navigating to her own profile page");
        await profilePage.goto(25);
        tester.isGoingTo(
            "As Jana Reed, ensure role, student Id are presented and campus, year level are not presented on her own profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Student ID");
        await profilePage.checkFieldIsNotVisible("Campus");
        await profilePage.checkFieldIsNotVisible("Year Level");

        // As Jana Reed, verify activity feed, goals, classes, groups tabs are presented & achievement and contacts tabs are not presented on her own profile page

        tester.isGoingTo(
            "As Jana Reed, ensure activity feed, goals, classes, groups tabs are presented on her own profile page",
        );
        await profilePage.checkTabIsVisible("activity");
        await profilePage.checkTabIsVisible("goals");
        await profilePage.checkTabIsVisible("classes");
        await profilePage.checkTabIsVisible("groups");
        tester.isGoingTo(
            "As Jana Reed, ensure achievement and contacts tabs are not presented on her own profile page",
        );
        await profilePage.checkTabIsNotVisible("achievements");
        await profilePage.checkTabIsNotVisible("contacts");

        // As Jana Reed , Navigating to Eden Reed profile and verify role is presented and student id, year level and campus are not presented
        tester.isGoingTo("Navigate to the Eden Reed profile page");
        await profilePage.goto(6);
        tester.isGoingTo(
            "As Jana Reed, ensure the role is presented and student id, year level and campus are not presented on Eden Reed's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsNotVisible("Student ID");
        await profilePage.checkFieldIsNotVisible("Year Level");
        await profilePage.checkFieldIsNotVisible("Campus");

        // As Jana Reed , verify all tabs are not presented on Eden Reed's profile page
        tester.isGoingTo("As Jana Reed, ensure all tabs are not presented on Eden Reed's profile page");
        await profilePage.checkTabIsNotVisible("activity");
        await profilePage.checkTabIsNotVisible("goals");
        await profilePage.checkTabIsNotVisible("classes");
        await profilePage.checkTabIsNotVisible("groups");
        await profilePage.checkTabIsNotVisible("achievements");
        await profilePage.checkTabIsNotVisible("contacts");

        // As Jana Reed , Navigating to Jack Coles and verify role is presented and campus , email fields are not presented
        tester.isGoingTo("Navigate to the Jack Coles profile page");
        await profilePage.goto(22);
        tester.isGoingTo(
            "As Jana Reed, ensure the role is presented and campus, email fields are not presented on Jack Coles's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsNotVisible("Email");
        await profilePage.checkFieldIsNotVisible("Campus");

        // As Jana Reed , Navigating to Jenni Reed and verify role is presented and campus field are not presented
        tester.isGoingTo("Navigate to the Jenni profile page");
        await profilePage.goto(27);
        tester.isGoingTo(
            "As Jana Reed, ensure role is presented and campus field are not presented on Jenni Reed's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");

        // Impersonate Jenni Reed
        tester.isGoingTo("Impersonating as Jenni Reed");
        await tester.impersonate(parent);

        // As Jenni Reed , Navigating to Jack Coles and verify Role is presented and campus field is not presented
        tester.isGoingTo("Navigate to the Jack Coles profile page");
        await profilePage.goto(22);
        tester.isGoingTo(
            "As Jenni Reed, ensure role is presented and campus field is not presented on Jack Coles's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");

        // As Jenni Reed , Navigating to Jana Reed and verify Role , Student id are presented and Campus, Year Level fields are not presented
        tester.isGoingTo("Navigate to the Jana Reed profile page");
        await profilePage.goto(25);
        tester.isGoingTo(
            "As Jenni Reed, ensure role, student id are presented and campus, year level fields are not presented on Jana Reed's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsVisible("Student ID");
        await profilePage.checkFieldIsNotVisible("Campus");
        await profilePage.checkFieldIsNotVisible("Year Level");

        // As Jenni Reed, verify activity feed, achievements, classes, groups and contacts tabs are presented & goals tab is not presented on Jana Reed's profile page
        tester.isGoingTo(
            "As Jenni Reed, ensure activity feed, achievements, classes, groups and contacts tabs are presented on Jana Reed's profile page",
        );
        await profilePage.checkTabIsVisible("activity");
        await profilePage.checkTabIsVisible("achievements");
        await profilePage.checkTabIsVisible("classes");
        await profilePage.checkTabIsVisible("groups");
        await profilePage.checkTabIsVisible("contacts");
        tester.isGoingTo("As Jenni Reed, ensure goals tab is not presented on Jana Reed's profile page");
        await profilePage.checkTabIsNotVisible("goals");

        // As Jenni Reed , Navigating to Eden Reed profile and verify role, campus are presented and student id, year level are not presented
        tester.isGoingTo("Navigate to the Eden Reed profile page");
        await profilePage.goto(6);
        tester.isGoingTo(
            "As Jenni Reed, ensure role, campus are presented and student id, year level are not presented on Eden Reed's profile page",
        );
        await profilePage.checkFieldIsVisible("Role");
        await profilePage.checkFieldIsNotVisible("Campus");
        await profilePage.checkFieldIsNotVisible("Student ID");
        await profilePage.checkFieldIsNotVisible("Year Level");

        // As Jenni Reed, verify activity feed, achievements, groups and contacts tabs are presented & goals and classes tabs are not presented on Eden Reed's profile page
        tester.isGoingTo(
            "As Jenni Reed, ensure activity feed, achievements, groups and contacts tabs are presented on Eden Reed's profile page",
        );
        await profilePage.checkTabIsVisible("activity");
        await profilePage.checkTabIsVisible("achievements");
        await profilePage.checkTabIsVisible("groups");
        await profilePage.checkTabIsVisible("contacts");
        tester.isGoingTo("As Jenni Reed, ensure goals and classes tabs are not presented on Eden Reed's profile page");
        await profilePage.checkTabIsNotVisible("goals");
        await profilePage.checkTabIsNotVisible("classes");
    });

    /**
     * TEST 3: Verify Portrait Image Availability & Visibility across Profile Pages
     * Step 1: Setup Portrait Image on profile page
     * -Student: Appears on- Secondary & Visible for - all
     * -Staff: Appears on- Secondary & Visible for - self
     * -Parent: Appears on- all & Visible for - none
     * Step 2: Verify Portrait Image Availability & Visibility on
     * - Jack Coles: Own, Jana Reed, Eden Reed, Jenni Reed, Rebecca white
     * - Jana Reed: Own, Jack Coles, Eden Reed, Jenni Reed
     * - Jenni Reed: Jana Reed, Eden Reed, Jack Coles
     */

    test("Portrait Image visibility check", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const profileEditor = new ProfileEditor(tester, page);

        const teacher = Common.USER_JACKCOLES;
        const student = Common.USER_JANAREED;
        const parent = Common.USER_JENNIREED;

        tester.isGoingTo("Navigate to the profile editor page");
        await profileEditor.goto();

        // 1: Set up Portrait Image on student profile page
        tester.isGoingTo("Set up Portrait Image on Student profile editor page");
        await profileEditor.clickFieldCog("student", "portrait");
        await profileEditor.clickFieldCheckbox("student", "portrait", "availability", "2");
        await profileEditor.clickFieldUpdateButton("student", "portrait");

        // 2: Set up Portrait Image on staff profile page
        tester.isGoingTo("Set up Portrait Image on Staff profile editor page");
        await profileEditor.clickRoleType("staff");
        await profileEditor.clickFieldCog("staff", "portrait");
        await profileEditor.clickFieldCheckbox("staff", "portrait", "visibility", "student");
        await profileEditor.clickFieldCheckbox("staff", "portrait", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("staff", "portrait", "visibility", "parent");
        await profileEditor.clickFieldCheckbox("staff", "portrait", "availability", "2");
        await profileEditor.clickFieldUpdateButton("staff", "portrait");

        // 3: Set up Portrait Image on Parent profile page
        tester.isGoingTo("Set up Portrait Image on Parent profile editor page");
        await profileEditor.clickRoleType("parent");
        await profileEditor.clickFieldCog("parent", "portrait");
        await profileEditor.clickFieldCheckbox("parent", "portrait", "visibility", "self");
        await profileEditor.clickFieldCheckbox("parent", "portrait", "visibility", "student");
        await profileEditor.clickFieldCheckbox("parent", "portrait", "visibility", "staff");
        await profileEditor.clickFieldCheckbox("parent", "portrait", "visibility", "parent");
        await profileEditor.clickFieldUpdateButton("parent", "portrait");

        // Impersonate Jack Coles
        await tester.impersonate(teacher);

        tester.isGoingTo("As Jack Coles, ensure profile page the image is presented on own profile page");
        await profilePage.goto(22);
        await profilePage.checkImagePresence("22");
        tester.isGoingTo("As Jack Coles, ensure profile page the image is presented on Jana Reed's profile page");
        await profilePage.goto(25);
        await profilePage.checkImagePresence("25");
        tester.isGoingTo("As Jack Coles, ensure profile page the image is not presented on Eden Reed's profile page");
        await profilePage.goto(6);
        await profilePage.checkImagePresence("0");
        tester.isGoingTo(
            "As Jack Coles, ensure profile page the image is not presented on rebecca White's profile page",
        );
        await profilePage.goto(24);
        await profilePage.checkImagePresence("0");
        tester.isGoingTo("As Jack Coles, ensure profile page the image is not presented on Jenni Reed's profile page");
        await profilePage.goto(27);
        await profilePage.checkImagePresence("0");

        // Impersonate as Jana Reed
        await tester.impersonate(student);
        tester.isGoingTo("As Jana Reed, ensure profile page the image is presented on her own profile page");
        await profilePage.goto(25);
        await profilePage.checkImagePresence("25");
        tester.isGoingTo("As Jana Reed, ensure profile page the image is not presented on Jack coles's profile page");
        await profilePage.goto(22);
        await profilePage.checkImagePresence("0");
        tester.isGoingTo("As Jana Reed, ensure profile page the image is not presented on Eden Reed's profile page");
        await profilePage.goto(6);
        await profilePage.checkImagePresence("0");
        tester.isGoingTo("As Jana Reed, ensure profile page the image is not presented on Jenni Reed's profile page");
        await profilePage.goto(27);
        await profilePage.checkImagePresence("0");

        // Impersonate Jenni Reed
        await tester.impersonate(parent);
        tester.isGoingTo("As Jenni Reed, ensure profile page the image is presented on her Jana Reed's profile page");
        await profilePage.goto(25);
        await profilePage.checkImagePresence("25");
        tester.isGoingTo("As Jenni Reed, ensure profile page the image is not presented on own profile page");
        await profilePage.goto(22);
        await profilePage.checkImagePresence("0");
        tester.isGoingTo("As Jenni Reed, ensure profile page the image is not presented on Eden Reed's profile page");
        await profilePage.goto(6);
        await profilePage.checkImagePresence("0");
    });

    /**
     * TEST 4: Verify Name Display Options visibility
     * Step 1: Set up name fields as below
     * -student tab: clear the field name
     * -staff tab: set up the field name with {firstname} {lastname} Test
     * -parent tab: set up the field name with {preferredname} {lastname}
     * Step 2: Navigate to the student, staff, parent profile pages and verify Name Display Options visibility
     * -the student name is presented
     * -the staff first and last names and Test are presented
     * -the parent preferred name and last name are presented
     */
    test("Name Display Options visibility check", async ({ tester, page }) => {
        const profileEditor = new ProfileEditor(tester, page);
        const profilePage = new ProfilePage(tester, page);

        // Setting up Name fields o profile editor page for Student, Staff & Parent
        tester.isGoingTo("Navigate to the student profile editor page");
        await profileEditor.goto();
        tester.isGoingTo("Clear the student's name field");
        await profileEditor.clearFieldName();
        tester.isGoingTo("Click the update button");
        await profileEditor.clickUpdateButton();
        tester.isGoingTo("Set up the field name with {firstname} {lastname} Test for staff");
        await profileEditor.clickRoleType("staff");
        await profileEditor.clearFieldName();
        await profileEditor.typeFieldName("{firstname} {lastname} Test");
        tester.isGoingTo("Click the update button");
        await profileEditor.clickUpdateButton();
        tester.isGoingTo("Set up the field name with {preferredname} {lastname} for parent");
        await profileEditor.clickRoleType("parent");
        await profileEditor.clearFieldName();
        await profileEditor.typeFieldName("{preferredname} {lastname}");
        tester.isGoingTo("Click the update button");
        await profileEditor.clickUpdateButton();

        // Navigate to the student, staff, parent profile pages for Name Display Options visibility checks
        tester.isGoingTo("Navigate to the student profile page");
        await profilePage.goto(25);
        tester.isGoingTo("Ensure the student name is presented");
        await profilePage.checkFieldNameIsPresented("Ms Jana Reed");
        tester.isGoingTo("Navigate to the staff profile page");
        await page.waitForTimeout(1000); // Added this timeout to allow sufficient time for the updated names to reflect on the profile screen
        await profilePage.goto(22);
        tester.isGoingTo("Ensure the staff first and last names and Test are presented");
        await profilePage.checkFieldNameIsPresented("Jack Coles Test");
        tester.isGoingTo("Navigate to the parent profile page");
        await page.waitForTimeout(1000); // Added this timeout to allow sufficient time for the updated names to reflect on the profile screen
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

        baseTester.isGoingTo("reset alt_email for Rebecca White");
        await db.updateInDatabase("user", { alt_email: null }, { id: 24 });

        // Restore the backed up tables
        baseTester.isGoingTo("restore the backed up tables");
        await Promise.all(TABLES_TO_RESTORE.map((table) => db.restoreTable(table)));
    });
});

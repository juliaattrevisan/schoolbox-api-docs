import { test } from "@schoolboxTest/playwright/support/integrations";
import CourseList from "@schoolboxTest/playwright/support/page/courses/CourseList";
import UnitPlanner from "@schoolboxTest/playwright/support/page/unitPlanner/UnitPlanner";

const USER_JACK_COLES = "Jack Coles";
const USER_ALISON_CHAN = "Ms Alison Chan";
const USER_STAFF_2 = "Schoolbox Staff 2";
const USER_STAFF_3 = "Schoolbox Staff 3";

const COURSE_YEAR_9_ENGLISH_ID = 6;
const COURSE_YEAR_9_FRENCH_ID = 2;
const COURSE_ELC_ID = 4;
const COURSE_ELC_NAME = "ELC";

const UNIT_ROMEO_AND_JULIET_ID = 15;
const UNIT_TO_KILL_A_MOCKINGBIRD_ID = 16;
const UNIT_PERSUASION_AND_MEDIA_ID = 17;
const UNIT_ROMEO_AND_JULIET_FOLDER_ID = 88;
const UNIT_PERSUASION_AND_MEDIA_FOLDER_ID = 102;

const ACTIVITY_ROMEO_AND_JULIET_ID = 59;
const ACTIVITY_PERSUASION_AND_MEDIA_ID = 158;

/**
 * This tests the permissions for the unit planner feature.
 * 1. Provision sales.tar.gz data
 * 2. Execute DOCKER=1 npx playwright test unitPlannerPermissions.spec.ts
 * - When the teacher has None access to the unit by navigating to the unit planner then they don't see the unit in the list.
 * - When the teacher has Read access to the unit by navigating to the unit planner then they see the unit in the list but cannot edit it.
 * - When the teacher has Write access to the unit by navigating to the unit planner then they see the unit in the list and can edit it.
 * - When users visit course unit planners, the create units button follows course folder and CMS permissions.
 */

test.describe("Unit Planner Permissions", () => {
    test.describe.configure({ mode: "serial" });

    test.beforeAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update perms and userAccess tables to set course, unit and CMS permissions for the unit planner permission tests",
        );
        await db.updateInDatabase("perms", { type: 1 }, { cid: 16, gid: 2 }); // Year 9 French - Read access
        await db.updateInDatabase("perms", { type: 0 }, { cid: 65, gid: 2 }); // ELC - None access
        await db.updateInDatabase("perms", { type: 1 }, { cid: 102, gid: 2 }); // Persuasion and the Media - Read access
        await db.updateInDatabase("perms", { type: 0 }, { cid: 89, gid: 2 }); // To Kill a Mockingbird unit - None access
        await db.haveInDatabase("userAccess", { uid: 43, service: 5, type: 1 }); // Alison Chan - Read access
        await db.haveInDatabase("userAccess", { uid: 66, service: 5, type: 2 }); // Staff 2 - Write access
        await db.haveInDatabase("userAccess", { uid: 67, service: 5, type: 3 }); // Staff 3 - Admin access
        // Romeo and Juliet unit has Write access for the teacher by provision
    });

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("Teacher can view and interact with units based on permissions", async ({ tester, page }) => {
        const unitPlannerPage = new UnitPlanner(tester, page);

        await tester.impersonateTeacher();

        await unitPlannerPage.goto(COURSE_YEAR_9_ENGLISH_ID);
        // checking whether the teacher can view or not the units under the course based on the permissions set in the beforeAll hook
        tester.isGoingTo(
            "the teacher can view the Persuasion and the Media unit and cannot view To Kill a Mockingbird unit",
        );
        await unitPlannerPage.checkUnitVisibility(UNIT_PERSUASION_AND_MEDIA_ID, true);
        await unitPlannerPage.checkUnitVisibility(UNIT_TO_KILL_A_MOCKINGBIRD_ID, false);

        // checking whether the teacher can create text boxes with the units based on the permissions set in the beforeAll hook
        tester.isGoingTo("the teacher is not able to create a text box in the Persuasion and the Media unit");
        await unitPlannerPage.checkCreateTextBoxButtonVisibility(UNIT_PERSUASION_AND_MEDIA_ID, false);
        tester.isGoingTo("the teacher can view the create text box button in the Romeo and Juliet unit");
        await unitPlannerPage.checkCreateTextBoxButtonVisibility(UNIT_ROMEO_AND_JULIET_ID, true);

        // checking whether the teacher can modify curriculum based on the permissions set in the beforeAll hook
        tester.isGoingTo("the teacher is not able to create curriculum in the Persuasion and the Media unit");
        await unitPlannerPage.checkCurriculumCreateModifyButtonVisibility(
            "create",
            UNIT_PERSUASION_AND_MEDIA_FOLDER_ID,
            false,
        );
        tester.isGoingTo("the teacher can view the modify curriculum link in the Romeo and Juliet unit");
        await unitPlannerPage.checkCurriculumCreateModifyButtonVisibility(
            "modify",
            UNIT_ROMEO_AND_JULIET_FOLDER_ID,
            true,
        );

        // checking whether the teacher can  manage unit capabilities based on the permissions set in the beforeAll hook
        tester.isGoingTo("the teacher is not able to manage unit capabilities in the Persuasion and the Media unit");
        await unitPlannerPage.clickUnitPlannerTabs("Rubric Capabilities");
        await unitPlannerPage.clickManageRubricCapabilitiesDropdown(UNIT_PERSUASION_AND_MEDIA_ID);
        await unitPlannerPage.checkRubricCapabilitiesOptions(UNIT_PERSUASION_AND_MEDIA_ID, true, false);
        tester.isGoingTo("the teacher can view and manage unit capabilities in the Romeo and Juliet unit");
        await unitPlannerPage.clickManageRubricCapabilitiesDropdown(UNIT_ROMEO_AND_JULIET_ID);
        await unitPlannerPage.checkRubricCapabilitiesOptions(UNIT_ROMEO_AND_JULIET_ID, true, true);

        // checking whether the teacher can  create activities based on the permissions set in the beforeAll hook
        tester.isGoingTo("the teacher is not able to edit activities in the Persuasion and the Media unit");
        await unitPlannerPage.clickUnitPlannerTabs("Learning Activities");
        await unitPlannerPage.checkCreateActivityButtonVisibility(UNIT_PERSUASION_AND_MEDIA_ID, false);
        tester.isGoingTo("the teacher can view and edit activities in the Romeo and Juliet unit");
        await unitPlannerPage.checkCreateActivityButtonVisibility(UNIT_ROMEO_AND_JULIET_ID, true);

        // check which options the teacher can see in the activity dropdown for each unit based on the permissions set in the beforeAll hook
        tester.isGoingTo(
            "the teacher can only view export under the activity menu in the Persuasion and the Media unit",
        );
        await unitPlannerPage.clickActivityDotMenu(ACTIVITY_PERSUASION_AND_MEDIA_ID);
        await unitPlannerPage.checkActivityMenuOptionVisibility("export", ACTIVITY_PERSUASION_AND_MEDIA_ID, true);
        await unitPlannerPage.checkActivityMenuOptionVisibility("modify", ACTIVITY_PERSUASION_AND_MEDIA_ID, false);
        await unitPlannerPage.checkActivityMenuOptionVisibility("delete", ACTIVITY_PERSUASION_AND_MEDIA_ID, false);

        tester.isGoingTo(
            "the teacher can view export, modify and delete options under the activity menu in the Romeo and Juliet unit",
        );
        await unitPlannerPage.clickActivityDotMenu(ACTIVITY_ROMEO_AND_JULIET_ID);
        await unitPlannerPage.checkActivityMenuOptionVisibility("export", ACTIVITY_ROMEO_AND_JULIET_ID, true);
        await unitPlannerPage.checkActivityMenuOptionVisibility("modify", ACTIVITY_ROMEO_AND_JULIET_ID, true);
        await unitPlannerPage.checkActivityMenuOptionVisibility("delete", ACTIVITY_ROMEO_AND_JULIET_ID, true);
    });

    test("Users can view the create units button based on course folder and CMS permissions", async ({
        tester,
        page,
    }) => {
        const courseList = new CourseList(tester, page);
        const unitPlannerPage = new UnitPlanner(tester, page);

        await tester.impersonateTeacher();

        tester.isGoingTo(
            `${USER_JACK_COLES} can view the create units button for Year 9 English, cannot view it for Year 9 French, and cannot view ELC in the course list`,
        );
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(COURSE_YEAR_9_ENGLISH_ID, true);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(COURSE_YEAR_9_FRENCH_ID, false);
        await courseList.goto();
        await courseList.verifyCourseNameInList(COURSE_ELC_NAME, false);

        tester.isGoingTo(`impersonate ${USER_ALISON_CHAN} who has CMS read permission`);
        await tester.impersonateViaUserProfilePage(USER_ALISON_CHAN);

        tester.isGoingTo(
            `${USER_ALISON_CHAN} can view the create units button for Year 9 English, but not for Year 9 French or ELC`,
        );
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(COURSE_YEAR_9_ENGLISH_ID, true);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(COURSE_YEAR_9_FRENCH_ID, false);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(COURSE_ELC_ID, false);

        tester.isGoingTo(`impersonate ${USER_STAFF_2} who has CMS write permission`);
        await tester.impersonateViaUserProfilePage(USER_STAFF_2);

        tester.isGoingTo(`${USER_STAFF_2} can view the create units button for Year 9 English, Year 9 French and ELC`);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(COURSE_YEAR_9_ENGLISH_ID, true);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(COURSE_YEAR_9_FRENCH_ID, true);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(COURSE_ELC_ID, true);

        tester.isGoingTo(`impersonate ${USER_STAFF_3} who has CMS admin permission`);
        await tester.impersonateViaUserProfilePage(USER_STAFF_3);

        tester.isGoingTo(`${USER_STAFF_3} can view the create units button for Year 9 English, Year 9 French and ELC`);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(COURSE_YEAR_9_ENGLISH_ID, true);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(COURSE_YEAR_9_FRENCH_ID, true);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(COURSE_ELC_ID, true);
    });

    test("Users can view unit 3-dot menu options based on unit folder and CMS permissions", async ({
        tester,
        page,
    }) => {
        const unitPlannerPage = new UnitPlanner(tester, page);

        tester.isGoingTo(`impersonate ${USER_JACK_COLES} who has none CMS permission`);
        await tester.impersonateTeacher();
        await unitPlannerPage.goto(COURSE_YEAR_9_ENGLISH_ID);

        tester.isGoingTo(
            `${USER_JACK_COLES} can view Course Markbook, Import, Modify and Delete in the Romeo and Juliet unit 3-dot menu`,
        );
        await unitPlannerPage.checkUnitMenuOptionsVisibility(
            UNIT_ROMEO_AND_JULIET_ID,
            ["course-link", "import-link", "modify-link", "delete-button"],
            [],
        );

        tester.isGoingTo(
            `${USER_JACK_COLES} can view only Course Markbook in the Persuasion and the Media unit 3-dot menu`,
        );
        await unitPlannerPage.checkUnitMenuOptionsVisibility(
            UNIT_PERSUASION_AND_MEDIA_ID,
            ["course-link"],
            ["import-link", "modify-link", "delete-button"],
        );

        tester.isGoingTo(`${USER_JACK_COLES} cannot view the To Kill a Mockingbird unit via the unit planner`);
        await unitPlannerPage.checkUnitVisibility(UNIT_TO_KILL_A_MOCKINGBIRD_ID, false);

        tester.isGoingTo(`impersonate ${USER_ALISON_CHAN} who has CMS read permission`);
        await tester.impersonateViaUserProfilePage(USER_ALISON_CHAN);
        await unitPlannerPage.goto(COURSE_YEAR_9_ENGLISH_ID);

        tester.isGoingTo(
            `${USER_ALISON_CHAN} can view all unit 3-dot menu options for Romeo and Juliet, and only Course Markbook for Persuasion and the Media and To Kill a Mockingbird`,
        );
        await unitPlannerPage.checkUnitMenuOptionsVisibility(
            UNIT_ROMEO_AND_JULIET_ID,
            ["course-link", "import-link", "modify-link", "delete-button"],
            [],
        );
        await unitPlannerPage.checkUnitMenuOptionsVisibility(
            UNIT_PERSUASION_AND_MEDIA_ID,
            ["course-link"],
            ["import-link", "modify-link", "delete-button"],
        );
        await unitPlannerPage.checkUnitMenuOptionsVisibility(
            UNIT_TO_KILL_A_MOCKINGBIRD_ID,
            ["course-link"],
            ["import-link", "modify-link", "delete-button"],
        );

        tester.isGoingTo(`impersonate ${USER_STAFF_2} who has CMS write permission`);
        await tester.impersonateViaUserProfilePage(USER_STAFF_2);
        await unitPlannerPage.goto(COURSE_YEAR_9_ENGLISH_ID);

        tester.isGoingTo(
            `${USER_STAFF_2} can view all unit 3-dot menu options for Romeo and Juliet, Persuasion and the Media, and To Kill a Mockingbird`,
        );
        await unitPlannerPage.checkUnitMenuOptionsVisibility(
            UNIT_ROMEO_AND_JULIET_ID,
            ["course-link", "import-link", "modify-link", "delete-button"],
            [],
        );
        await unitPlannerPage.checkUnitMenuOptionsVisibility(
            UNIT_PERSUASION_AND_MEDIA_ID,
            ["course-link", "import-link", "modify-link", "delete-button"],
            [],
        );
        await unitPlannerPage.checkUnitMenuOptionsVisibility(
            UNIT_TO_KILL_A_MOCKINGBIRD_ID,
            ["course-link", "import-link", "modify-link", "delete-button"],
            [],
        );

        tester.isGoingTo(`impersonate ${USER_STAFF_3} who has CMS admin permission`);
        await tester.impersonateViaUserProfilePage(USER_STAFF_3);
        await unitPlannerPage.goto(COURSE_YEAR_9_ENGLISH_ID);

        tester.isGoingTo(
            `${USER_STAFF_3} can view all unit 3-dot menu options for Romeo and Juliet, Persuasion and the Media, and To Kill a Mockingbird`,
        );
        await unitPlannerPage.checkUnitMenuOptionsVisibility(
            UNIT_ROMEO_AND_JULIET_ID,
            ["course-link", "import-link", "modify-link", "delete-button"],
            [],
        );
        await unitPlannerPage.checkUnitMenuOptionsVisibility(
            UNIT_PERSUASION_AND_MEDIA_ID,
            ["course-link", "import-link", "modify-link", "delete-button"],
            [],
        );
        await unitPlannerPage.checkUnitMenuOptionsVisibility(
            UNIT_TO_KILL_A_MOCKINGBIRD_ID,
            ["course-link", "import-link", "modify-link", "delete-button"],
            [],
        );
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update perms and userAccess tables to restore original permissions as per provision");
        await db.updateInDatabase("perms", { type: 2 }, { cid: 16, gid: 2 }); // Year 9 French - set back to write access
        await db.updateInDatabase("perms", { type: 2 }, { cid: 65, gid: 2 }); // ELC - set back to write access
        await db.updateInDatabase("perms", { type: 2 }, { cid: 102, gid: 2 }); // Persuasion and the Media - set back to write access
        await db.updateInDatabase("perms", { type: 2 }, { cid: 89, gid: 2 }); // To Kill a Mockingbird unit - set back to write access
        await db.dontHaveInDatabase("userAccess", { uid: 43, service: 5, type: 1 });
        await db.dontHaveInDatabase("userAccess", { uid: 66, service: 5, type: 2 });
        await db.dontHaveInDatabase("userAccess", { uid: 67, service: 5, type: 3 });
    });
});

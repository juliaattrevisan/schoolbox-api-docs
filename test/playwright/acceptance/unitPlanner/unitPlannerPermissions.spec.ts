import { test } from "@schoolboxTest/playwright/support/integrations";
import CourseList from "@schoolboxTest/playwright/support/page/courses/CourseList";
import UnitPlanner from "@schoolboxTest/playwright/support/page/unitPlanner/UnitPlanner";

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
        await db.haveInDatabase("userAccess", { uid: 43, service: 5, type: 1 }); // Alisson Chan - Read access
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

        await unitPlannerPage.goto(6);
        // checking whether the teacher can view or not the units under the course based on the permissions set in the beforeAll hook
        tester.isGoingTo(
            "the teacher can view the Persuasion and the Media unit and cannot view To Kill a Mockingbird unit",
        );
        await unitPlannerPage.checkUnitVisibility(17, true); // ID 17 corresponds to "Persuasion and the Media"
        await unitPlannerPage.checkUnitVisibility(16, false); // ID 16 corresponds to "To Kill a Mockingbird"

        // checking whether the teacher can create text boxes with the units based on the permissions set in the beforeAll hook
        tester.isGoingTo("the teacher is not able to create a text box in the Persuasion and the Media unit");
        await unitPlannerPage.checkCreateTextBoxButtonVisibility(17, false);
        tester.isGoingTo("the teacher can view the create text box button in the Romeo and Juliet unit");
        await unitPlannerPage.checkCreateTextBoxButtonVisibility(15, true); // unit ID 15 corresponds to "Romeo and Juliet"

        // checking whether the teacher can modify curriculum based on the permissions set in the beforeAll hook
        tester.isGoingTo("the teacher is not able to create curriculum in the Persuasion and the Media unit");
        await unitPlannerPage.checkCurriculumCreateModifyButtonVisibility("create", 102, false);
        tester.isGoingTo("the teacher can view the modify curriculum link in the Romeo and Juliet unit");
        await unitPlannerPage.checkCurriculumCreateModifyButtonVisibility("modify", 88, true);

        // checking whether the teacher can  manage unit capabilities based on the permissions set in the beforeAll hook
        tester.isGoingTo("the teacher is not able to manage unit capabilities in the Persuasion and the Media unit");
        await unitPlannerPage.clickUnitPlannerTabs("Rubric Capabilities");
        await unitPlannerPage.clickManageRubricCapabilitiesDropdown(17);
        await unitPlannerPage.checkRubricCapabilitiesOptions(17, true, false);
        tester.isGoingTo("the teacher can view and manage unit capabilities in the Romeo and Juliet unit");
        await unitPlannerPage.clickManageRubricCapabilitiesDropdown(15);
        await unitPlannerPage.checkRubricCapabilitiesOptions(15, true, true);

        // checking whether the teacher can  create activities based on the permissions set in the beforeAll hook
        tester.isGoingTo("the teacher is not able to edit activities in the Persuasion and the Media unit");
        await unitPlannerPage.clickUnitPlannerTabs("Learning Activities");
        await unitPlannerPage.checkCreateActivityButtonVisibility(17, false);
        tester.isGoingTo("the teacher can view and edit activities in the Romeo and Juliet unit");
        await unitPlannerPage.checkCreateActivityButtonVisibility(15, true);

        // check which options the teacher can see in the activity dropdown for each unit based on the permissions set in the beforeAll hook
        tester.isGoingTo(
            "the teacher can only view export under the activity menu in the Persuasion and the Media unit",
        );
        await unitPlannerPage.clickActivityDotMenu(158);
        await unitPlannerPage.checkActivityMenuOptionVisibility("export", 158, true);
        await unitPlannerPage.checkActivityMenuOptionVisibility("modify", 158, false);
        await unitPlannerPage.checkActivityMenuOptionVisibility("delete", 158, false);

        tester.isGoingTo(
            "the teacher can view export, modify and delete options under the activity menu in the Romeo and Juliet unit",
        );
        await unitPlannerPage.clickActivityDotMenu(59);
        await unitPlannerPage.checkActivityMenuOptionVisibility("export", 59, true);
        await unitPlannerPage.checkActivityMenuOptionVisibility("modify", 59, true);
        await unitPlannerPage.checkActivityMenuOptionVisibility("delete", 59, true);
    });

    test("Users can view the create units button based on course folder and CMS permissions", async ({
        tester,
        page,
    }) => {
        const courseList = new CourseList(tester, page);
        const unitPlannerPage = new UnitPlanner(tester, page);

        await tester.impersonateTeacher();

        tester.isGoingTo(
            "Jack Coles can view the create units button for Year 9 English, cannot view it for Year 9 French, and cannot view ELC in the course list",
        );
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(6, true);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(2, false);
        await courseList.goto();
        await courseList.verifyCourseNameInList("ELC", false);

        tester.isGoingTo("impersonate Alison Chan who has CMS read permission");
        await tester.impersonateViaUserProfilePage("Ms Alison Chan");

        tester.isGoingTo(
            "Alison Chan can view the create units button for Year 9 English, but not for Year 9 French or ELC",
        );
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(6, true);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(2, false);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(4, false);

        tester.isGoingTo("impersonate Schoolbox Staff 2 who has CMS write permission");
        await tester.impersonateViaUserProfilePage("Schoolbox Staff 2");

        tester.isGoingTo(
            "Schoolbox Staff 2 can view the create units button for Year 9 English, Year 9 French and ELC",
        );
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(6, true);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(2, true);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(4, true);

        tester.isGoingTo("impersonate Schoolbox Staff 3 who has CMS admin permission");
        await tester.impersonateViaUserProfilePage("Schoolbox Staff 3");

        tester.isGoingTo(
            "Schoolbox Staff 3 can view the create units button for Year 9 English, Year 9 French and ELC",
        );
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(6, true);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(2, true);
        await unitPlannerPage.navigateAndCheckCreateUnitsButton(4, true);
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

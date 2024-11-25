/**
 * creates a task with participation and learning plan
 * check as admin that the learning plan is presented in the assessment modify page
 * impersonates a student in the learning plan and check the proper due date is presented
 * impersonates a student not in the learning plan and check the proper due date is presented
 *
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. npx playwright test participationPlan
 *
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Common from "@schoolboxTest/playwright/support/page/Common";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import StatusPage from "@schoolboxTest/playwright/support/page/dueWork/StatusPage";
import Folder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import ManageGroup from "@schoolboxTest/playwright/support/page/group/ManageGroup";
import Resources from "@schoolboxTest/playwright/support/page/resources/Resources";

test.describe("Participation Plan Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.logout();
        await tester.loginAsAdmin();
    });

    test("Participation Plan", async ({ tester, page, db }) => {
        const addComponent = new Homepage(page);
        const dueworkForm = new AssessmentForm(tester, page);
        const statusPage = new StatusPage(tester, page);
        const folder = new Folder(page);
        const manageGroup = new ManageGroup(page);
        const resources = new Resources(page);

        const student1 = Common.USER_EDENREED;
        const student2 = Common.USER_JANAREED;
        const teacher = "Jack Coles";
        const teacherId = Common.USER_ID_JACKCOLES;

        const folderName = "Participation and Learning Plan Homepage";
        const homepageTemplateOption = "Blank";

        tester.isGoingTo("create a folder by typing a name and selecting a homepage template");
        await folder.goto();
        await folder.typeName(folderName);
        await folder.setHomepageTemplate(homepageTemplateOption);
        tester.isGoingTo("set write access permission for Administration and Teacher");
        await folder.setAccessPermission("1", "write");
        await folder.setAccessPermission("2", "write");
        tester.isGoingTo("set read access permission for senior student");
        await folder.setAccessPermission("3", "read");
        tester.isGoingTo("set read access permission for parent");
        await folder.setAccessPermission("4", "read");
        tester.isGoingTo("set read access permission for junior student");
        await folder.setAccessPermission("5", "read");
        tester.isGoingTo("set read access permission for middle student");
        await folder.setAccessPermission("6", "read");
        tester.isGoingTo("type a school code and click Create button");
        await folder.submit();

        tester.isGoingTo("Navigate to the new folder store its location for later tests");
        await folder.gotoResources();
        await resources.clickFolderName(folderName);
        await resources.clickMenuItem("home");
        const homepageUrl = page.url();

        tester.isGoingTo("Add Jack Coles, Eden Reed and Jana Reed into the group via manage group page");
        await page.locator("[data-test=sbx-pop-out-tab-control-button]").click();
        await page.getByTestId("homepage-actions-folder-manageGroup").click();
        await tester.common.waitForPageLoad();
        await manageGroup.search(teacher);
        await manageGroup.selectUser(teacherId);
        await manageGroup.clickFromRadioButton("role");
        await manageGroup.clickAddMembers();
        await page.waitForTimeout(1000); // wait to be able to update the db
        tester.isGoingTo("Make Jack Coles the group admin via database");
        const folderId = (await db.grabFromDatabase("folder", "id", {
            deleted_at: null,
            "name like": folderName,
        })) as number;
        await db.updateInDatabase("userAccess", { type: 3 }, { sid: folderId, uid: "22" });
        tester.isGoingTo("Add Eden Reed into the group");
        await manageGroup.search(student1);
        await manageGroup.selectUser(6);
        await manageGroup.clickFromRadioButton("role");
        await manageGroup.clickAddMembers();
        tester.isGoingTo("Add Jana Reed into the group");
        await manageGroup.clickFromRadioButton("name");
        await manageGroup.search(student2);
        await manageGroup.selectUser(25);
        await manageGroup.clickFromRadioButton("role");
        await manageGroup.clickAddMembers();

        tester.isGoingTo("Add the task component in the homepage");
        await page.goto(homepageUrl);
        await addComponent.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_LEARNING, "Tasks");

        tester.isGoingTo("Create a task with a learning plan");
        await page.getByTestId("component-task-empty-create-button").click();
        await dueworkForm.setDueDate(new Date("2030-08-03"));
        await page.locator('input[id="title"]').fill("Task Participation and Learning Plan");
        await dueworkForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        const dateLearningPlan = "03/10/2030";
        await page.waitForTimeout(2000);
        await dueworkForm.createLearningPlan(6, "Test learning plan", dateLearningPlan, true);
        await dueworkForm.create();
        await expect(page.locator("h2:has-text('Tasks')")).toBeVisible();

        tester.isGoingTo("As admin check if the learning plan is presented under Participation and Learning Plans");
        await page.locator("a:has-text('Task Participation and Learning Plan')").click();
        await statusPage.clickJellyTot();
        await statusPage.clickJellyTotOption("modify");
        await expect(page.locator("h1:has-text('Update Task Participation and Learning Plan')")).toBeVisible();
        await expect(page.getByTestId("assessment-form-button-submit-Update")).toBeEnabled();
        await dueworkForm.clickModifyLearningPlan();
        await expect(page.locator('ul[id="plan-options"]')).toHaveText("Test learning plan");

        tester.isGoingTo("Get logged in as Eden Reed and ensure the due date is the same as per the learning plan");
        await tester.impersonate(student1);
        await page.goto(homepageUrl);
        await expect(page.locator("h2:has-text('Tasks')")).toBeVisible();
        await expect(page.locator("time:has-text('Oct 3, 2030')")).toBeVisible();

        tester.isGoingTo("Get logged in as Jana Reed and ensure the due date is not from the learning plan");
        await tester.impersonate(student2);
        await page.goto(homepageUrl);
        await expect(page.locator("h2:has-text('Tasks')")).toBeVisible();
        await expect(page.locator("time:has-text('Aug 3, 2030')")).toBeVisible();
    });

    /**
     * Soft deleting data to not impact the tests
     */
    test.afterAll(async ({ db }) => {
        await db.updateInDatabase(
            "folder",
            { deleted_at: "2022-01-15 11:22:34" },
            { "name like": "Participation and Learning Plan Homepage" },
        );
        await db.updateInDatabase(
            "assessment",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Task Participation and Learning Plan" },
        );
    });
});

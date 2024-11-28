import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CreateLessonPlanTemplate from "@schoolboxTest/playwright/support/page/admin/lms/LessonPlanTemplates/CreateLessonPlanTemplate";
import LessonPlanTemplateList from "@schoolboxTest/playwright/support/page/admin/lms/LessonPlanTemplates/LessonPlanTemplateList";
import CourseModify from "@schoolboxTest/playwright/support/page/courses/CourseModify";
import CourseList from "@schoolboxTest/playwright/support/page/courses/CourseList";
import ImportCourseIntoClass from "@schoolboxTest/playwright/support/page/courses/ImportCourseIntoClass";
import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import ManageGroup from "@schoolboxTest/playwright/support/page/group/ManageGroup";
import CreateLessonPlan from "@schoolboxTest/playwright/support/page/lessonPlan/CreateLessonPlan";
import TemplateList from "@schoolboxTest/playwright/support/page/lessonPlan/TemplateList";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";
import Resources from "@schoolboxTest/playwright/support/page/resources/Resources";

/**
 * create a lesson plan, check if the teacher and student can view the fields as per its visibility configuration
 * 1. Provision sales.tar.gz data
 * 2. npx playwright test lessonPlanTemplates.spec.ts
 */

/**
 * covenience function to create a homepage and a lesson plan template
 */

/**
 * Create a lesson plan template
 * @param tester - The tester
 * @param page - The page
 * @param db - The database helper
 * @param templateName - The template name
 * @param folderName - The folder name
 * @param teacher - The teacher
 * @param student - The student
 */
async function createLessonPlanTemplate(
    tester: AcceptanceTester,
    page: Page,
    db: DatabaseHelper,
    templateName: string,
    folderName: string,
    teacher: string,
    student: string,
): Promise<void> {
    const templates = new CreateLessonPlanTemplate(page, tester);
    const templateList = new LessonPlanTemplateList(tester, page);
    const createFolder = new CreateFolder(page);
    const manageGroup = new ManageGroup(page);
    const resources = new Resources(page);

    tester.isGoingTo("Click add a lesson plan template button in the lesson plan template lists page");
    await templateList.clickAddLessonPlanTemplateButton();
    tester.isGoingTo("Type a template name,add a field name and body and save it");
    await templates.typeName(templateName);
    await templates.typeFieldTile(1, "Automation Field 1");
    await templates.fillBody(1, "Automation Body 1");
    tester.isGoingTo("Add a new field, type a filed title and body and save it");
    await templates.clickAddNewFieldButton();
    await templates.typeFieldTile(2, "Automation Field 2 not visible to students");
    await templates.fillBody(2, "Automation Body 2 not visible to students");
    await templates.clickCheckboxVisibleStudentsCreatingTemplate(2);
    await templates.clickSaveButton();
    tester.isGoingTo("Check the lesson pan template is presented in the template list");
    await expect(page.locator("div.admin-content")).toContainText(templateName);

    tester.isGoingTo("Create a homepage");
    await createFolder.goto();
    await page.waitForLoadState("domcontentloaded");
    const homepageTemplateOption = "Blank";
    tester.isGoingTo("type a folder name, select a template option and set access permissions");
    await createFolder.typeName(folderName);
    await createFolder.setHomepageTemplate(homepageTemplateOption);
    await createFolder.setAccessPermission(1, "write");
    await createFolder.setAccessPermission(2, "write");
    await createFolder.setAccessPermission(3, "read");
    await createFolder.setAccessPermission(4, "read");
    await createFolder.setAccessPermission(5, "read");
    await createFolder.setAccessPermission(6, "read");
    await createFolder.submit();

    tester.isGoingTo("Navigate to the homepage");
    await page.goto("https://vagrant.dev.schoolbox.cloud/resources/");
    await expect(page.locator(`li[title="${folderName}"]`)).toBeVisible();
    const folderId = (await db.grabFromDatabase("folder", "id", { deleted_at: null, name: folderName })) as string;
    expect(folderId).not.toBe(null);
    await resources.clickFolderName(folderName);

    await page.waitForLoadState("domcontentloaded");
    await resources.clickMenuItem("home");
    await page.waitForLoadState("domcontentloaded");
    tester.isGoingTo("Add Jack Coles into the group via the manage group page");
    await page.getByTestId("sbx-pop-out-tab-control-button").click(); // clicking the homepage 3-dot menu
    await page.getByTestId("homepage-actions-folder-manageGroup").click(); // clicking Manage group option
    await page.waitForLoadState("domcontentloaded");
    await manageGroup.search(teacher);
    await manageGroup.selectUser(22);
    await manageGroup.clickAddMembers();
    await page.waitForTimeout(3000);
    tester.isGoingTo("Make Jack coles the group admin via database");
    await db.updateInDatabase("userAccess", { type: "3" }, { sid: folderId, uid: "22" });
    tester.isGoingTo("Add Eden Reed into the group");
    await manageGroup.search(student);
    await manageGroup.selectUser(6);
    await manageGroup.clickAddMembers();
}

test.describe("Lesson Plan Activity", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });
    /**
     * Preparations
     *
     * get logged in
     * navigate to  /adminv2/lesson-plan/template/create
     */
    test.beforeEach(async ({ tester, page }) => {
        await tester.logout();
        await tester.loginAsAdmin();
        const templateList = new LessonPlanTemplateList(tester, page);
        await templateList.goto();
    });

    /**
     * Soft deleting data to not impact the tests
     */

    test.beforeAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update delete_at for the folder and lesson plan table so it does not impact the tests");
        await db.updateInDatabase(
            "lesson_plan_template_field",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Automation Field%" },
        );
        await db.updateInDatabase(
            "lesson_plan_template",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Automation Template%" },
        );
        await db.updateInDatabase(
            "lesson_plan_field",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Automation Field%" },
        );
        await db.updateInDatabase("lesson_plan", { deleted_at: "2022-01-15 11:22:34" }, { created_by: "1" });
        await db.updateInDatabase(
            "assessment",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Lesson Plan Automation Test%" },
        );
        await db.updateInDatabase(
            "folder",
            { deleted_at: "2022-01-15 11:22:34" },
            { "name like": "Test Class Homepage Lesson Plan%" },
        );
    });

    /**
     * create a lesson plan template with a field visible to student and a field that is not visible to students
     * create  lesson plan using the lesson plan template above
     */
    test("Create Lesson Plan", async ({ tester, page, db }) => {
        const courseModify = new CourseModify(page, tester);
        const templateList = new TemplateList(tester, page);
        const createLessonPlan = new CreateLessonPlan(tester, page);
        // const createLessonPlanTemplate = new CreateLessonPlanTemplate(page, tester);
        const templateName = "Automation Template to create a lesson plan";
        const lessonPlanName = "Lesson Plan Automation Test 1";
        const folderName = "Test Class Homepage Lesson Plan";

        const teacher = "Jack Coles";
        const student = "Eden Reed";

        tester.isGoingTo("create a homepage and a lesson plan template");

        await createLessonPlanTemplate(tester, page, db, templateName, folderName, teacher, student);
        tester.isGoingTo("navigate to the  Year 9 English course modify page");
        await courseModify.goto(6);
        tester.isGoingTo("click the arrow down for Romeo and Juliet unit");
        await courseModify.clickUnitArrow(15);
        tester.isGoingTo("click New Lesson PLan button to create a lesson plan");
        await courseModify.clickNewLessonPlanButton("lesson-plan");
        await page.waitForLoadState("domcontentloaded");
        const lessonPlanTemplateId = (await db.grabFromDatabase("lesson_plan_template", "id", {
            deleted_at: null,
            title: templateName,
        })) as number;
        await templateList.clickTemplateLink(lessonPlanTemplateId);
        await tester.common.clickSubmitButton();
        await createLessonPlan.typeName(lessonPlanName);
        await createLessonPlan.clickCreateButton();
        tester.isGoingTo("check if the lesson plan is presented");
        await expect(page.locator("#linked-activities-container")).toBeVisible();
        await expect(page.locator("ul.breadcrumb")).toContainText(lessonPlanName);
    });

    /**
     * importing the course with a lesson plan  into a homepage
     * check if the teacher can view all the lesson plan fields
     * check if the student is able to view the visible to student fields
     * check if the students cannot see the not visible to student fields
     */

    test("Lesson Plan Fileds Visibility", async ({ tester, page, db }) => {
        const courseList = new CourseList(page);
        const importCourseIntoClass = new ImportCourseIntoClass(page);
        const lessonPlanName = "Lesson Plan Automation Test 1";
        const folderName = "Test Class Homepage Lesson Plan";

        const teacher = "Mr Jack Coles";
        const student = "Ms Eden Reed";

        tester.isGoingTo(
            "navigate to course list and click the 3-dot menu for Year 9 English course to import into a class page",
        );
        await courseList.goto();
        await courseList.clickCourseDotMenu(6);
        tester.isGoingTo("click the option Import into classes from the dropdown menu");
        await courseList.clickImportIntoClass(6);
        tester.isGoingTo("type a class name, select it from the results and click the Next button");
        await importCourseIntoClass.typeClassName(folderName);
        await importCourseIntoClass.clickClassName();
        await importCourseIntoClass.clickSubmit("Next");
        tester.isGoingTo("tick Romeo and Juliet unit checkbox, fill open and close dates and hit Next");
        await importCourseIntoClass.tickUnitCheckbox(1);
        const openDate = new Date();
        openDate.setDate(openDate.getDate() - 2); // 2 days ago
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 30); // 30 days from now
        await importCourseIntoClass.fillOpenDate(1, openDate);
        await importCourseIntoClass.insertDueDate(1, dueDate);
        await importCourseIntoClass.clickSubmit("Next");
        tester.isGoingTo("untick Romeo and Juliet Key Quote Activity in the step 3 Coursework");
        await importCourseIntoClass.tickAssessmentCheckbox(52);
        tester.isGoingTo("untick Present a scene of Romeo and Juliet in the step 3 Coursework");
        await importCourseIntoClass.tickAssessmentCheckbox(146);
        tester.isGoingTo("untick Post-Unit Quiz in the step 3 Coursework");
        await importCourseIntoClass.tickAssessmentCheckbox(151);
        tester.isGoingTo(
            "click button Import, navigate to the homepage and check that Romeo and Juliet unit is presented in the page",
        );
        await importCourseIntoClass.clickSubmit("Import");

        tester.isGoingTo("check if the lesson plan is presented in the homepage");
        const folderId = (await db.grabFromDatabase("folder", "id", { name: folderName, deleted_at: null })) as number;
        await page.waitForTimeout(3000);
        await page.goto(`/homepage/${folderId}`);
        await expect(page.locator('ul[class="information-list"]')).toContainText("Romeo and Juliet");
        await expect(page.locator('ul[class="information-list"]')).toContainText(lessonPlanName);
        tester.isGoingTo("as a student view the lesson plan");
        await tester.impersonateViaModifyUserPage(student);
        await page.goto(`/homepage/${folderId}`);
        await page.waitForTimeout(3000);
        await page.click("a[title='Lesson Plan']");
        tester.isGoingTo(
            "check if student can view the visible field and cannot view the not visible fields to students",
        );
        await page.waitForTimeout(3000);
        await expect(page.locator("#lessonPlan-student")).toContainText("Automation Field 1");
        await expect(page.locator("#lessonPlan-student")).not.toContainText(
            "Automation Field 2 not visible to students",
        );
        tester.isGoingTo("as a teacher view the lesson plan");
        await tester.impersonateViaModifyUserPage(teacher);
        await page.goto(`/homepage/${folderId}`);
        await page.waitForTimeout(3000);
        await page.click("a[title='Lesson Plan']");
        tester.isGoingTo("check if teacher can view all the lesson plan fields");
        await expect(page.locator("#lessonPlan-staff")).toContainText("Automation Field 1");
        await expect(page.locator("#lessonPlan-staff")).toContainText("Automation Field 2 not visible to students");
    });

    /**
     * Soft deleting data to not impact the tests
     */

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update delete_at for the folder and lesson plan table so it does not impact the tests");
        await db.updateInDatabase(
            "lesson_plan_template_field",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Automation Field%" },
        );
        await db.updateInDatabase(
            "lesson_plan_template",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Automation Template%" },
        );
        await db.updateInDatabase(
            "lesson_plan_field",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Automation Field%" },
        );
        await db.updateInDatabase("lesson_plan", { deleted_at: "2022-01-15 11:22:34" }, { created_by: "1" });
        await db.updateInDatabase(
            "assessment",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Lesson Plan Automation Test%" },
        );
        await db.updateInDatabase(
            "folder",
            { deleted_at: "2022-01-15 11:22:34" },
            { "name like": "Test Class Homepage Lesson Plan%" },
        );
    });
});

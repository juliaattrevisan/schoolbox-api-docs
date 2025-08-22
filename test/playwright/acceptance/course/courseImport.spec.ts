/**
 * This test imports the course into a class, copies an activity into a class
 */
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import CourseModify from "@schoolboxTest/playwright/support/page/courses/CourseModify";
import CourseList from "@schoolboxTest/playwright/support/page/courses/CourseList";
import ImportCourseIntoClass from "@schoolboxTest/playwright/support/page/courses/ImportCourseIntoClass";
import CreateLessonPlan from "@schoolboxTest/playwright/support/page/lessonPlan/CreateLessonPlan";
import TemplateList from "@schoolboxTest/playwright/support/page/lessonPlan/TemplateList";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import ManageGroup from "@schoolboxTest/playwright/support/page/group/ManageGroup";
import dayjs from "dayjs";

test.describe("Course import tests", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    /**
     * Import a course into a class
     * - navigate to /resource/folder/0/create
     * - create a folder
     * - navigate to /course/list
     * - import year 9 English into the homepage created above in the before function
     * - add a project and lesson plan under the year 9 english course
     * - export only romeo and Juliet
     * - untick a quiz, due work and task before importing
     * - as a teacher ensure the course is presented in the homepage with the proper details: activities dates, number of students, title
     */
    test("Import course into class", async ({ tester, page, db }) => {
        const createFolder = new CreateFolder(tester, page);
        const courseList = new CourseList(page);
        const importCourseIntoClass = new ImportCourseIntoClass(page);
        const courseModify = new CourseModify(page, tester);
        const createLessonPlan = new CreateLessonPlan(tester, page);
        const templateList = new TemplateList(tester, page);
        const assessmentForm = new AssessmentForm(tester, page);
        const manageGroup = new ManageGroup(page);

        const folderName = `Automation Course Import Folder1`;
        const homepageTemplateOption = "Course";
        const schoolCode = "test1";
        const lessonPlanName = "Export Activity Lesson Plan for Romeo and Juliet";
        const projectName = "Export Activity Project for Romeo and Juliet";
        const dueWorkName = "Export Activity Test Due Work Project";

        // sets write access permission for Administration and Teacher, read access permission for senior student, parent, junior student and middle student
        await createFolder.createFolder(
            folderName,
            homepageTemplateOption,
            db,
            [
                [1, "write"],
                [2, "write"],
                [3, "read"],
                [4, "read"],
                [5, "read"],
                [6, "read"],
            ],
            schoolCode,
            true,
        );

        // add members to the folder
        const folderId = (await db.grabFromDatabase("folder", "id", {
            deleted_at: null,
            "name like": folderName,
        })) as number;
        await manageGroup.setAccessPermission("type-3");
        await manageGroup.clickUpdateButton();
        tester.isGoingTo("add Jana Reed, Eden Reed, Leon Reed and Tim Reed into the group");
        await manageGroup.search("reed");
        await manageGroup.selectUser(45);
        await manageGroup.selectUser(6);
        await manageGroup.selectUser(25);
        await manageGroup.selectUser(26);
        await manageGroup.clickFromRadioButton("role");
        await manageGroup.clickAddMembers();

        tester.isGoingTo("modify the course, add add a lesson plan and project with a task under it");
        await courseModify.goto(6); // Navigate to Year 9 English course modify page
        await courseModify.clickUnitArrow(15); // Click the 3-dot menu for Romeo and Juliet unit
        await courseModify.clickNewActivityButton("lesson-plan");
        await templateList.clickTemplateLink(3);
        await tester.common.clickSubmitButton();
        await createLessonPlan.typeName(lessonPlanName);
        await createLessonPlan.clickCreateButton();
        tester.isGoingTo("check if the lesson plan is presented");
        await expect(page.locator("#linked-activities-container")).toBeVisible();
        await expect(page.locator("ul.breadcrumb")).toContainText(lessonPlanName);
        await createLessonPlan.clickLinkActivity();
        await createLessonPlan.clickUnitMenu(15); // Click the unit Romeo and Juliet expand arrow
        await createLessonPlan.tickAssessmentCheckbox("52"); // Tick the assessment checkbox Romeo and Juliet Key Quote Activity
        await createLessonPlan.tickAssessmentCheckbox("146"); // Tick the assessment checkbox Present a scene of Romeo and Juliet
        await createLessonPlan.clickLinkActivityButton(); // Click Link Activity button

        tester.isGoingTo("add a project with a due work under it");
        await courseModify.goto(6); // Navigate to Year 9 English course modify page
        await courseModify.clickUnitArrow(15); // Click the 3-dot menu for Romeo and Juliet unit
        await courseModify.clickNewActivityButton("project");
        await page.locator("input#title").fill(projectName);
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.clickCreate();
        await page.getByTestId("project-create-activity-link").click();
        await page.locator(`label[for="due-work"]`).click();
        await assessmentForm.setTitle(dueWorkName);
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.clickCreate();
        await expect(page.locator("#assessment-list")).toBeVisible();

        tester.isGoingTo("navigate to the course list and click the option Import into classes from the dropdown menu");
        await courseList.goto();
        await courseList.clickCourseDotMenu(6); // Click the 3-dot menu for Year 9 English course
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

        await new Promise((resolve) => {
            setTimeout(resolve, 3000);
        }); // Wait for import to complete

        await page.goto(`/homepage/${folderId.toString()}`);

        await expect(page.locator("#container")).toContainText("Romeo and Juliet"); // TODO: Use data-test

        tester.isGoingTo(
            "as Jack Coles check the course outline: course name, unit name, activity title, dates, number of students",
        );
        await tester.impersonate("Jack Coles");
        await page.goto(`/homepage/${folderId.toString()}`);
        await expect(page.locator('a[href="/homepage/87"]')).toContainText("Year 9 English");
        await expect(page.locator('a[href="/homepage/88"]')).toContainText("Romeo and Juliet");
        tester.isGoingTo(
            "check one activity of each type is presented in the course outline and the due work under the project is not presented",
        );

        await expect(page.getByTestId("course-outline-assessment-title-Pre-unit Quiz")).toContainText("Pre-unit Quiz");
        await expect(page.getByTestId("course-outline-assessment-title-Post to Forum: Prologue")).toContainText(
            "Post to Forum: Prologue",
        );
        await expect(page.getByTestId("course-outline-assessment-title-Essay - Final Assessment")).toContainText(
            "Essay - Final Assessment",
        );
        await expect(page.getByTestId(`course-outline-assessment-title-${lessonPlanName}`)).toContainText(
            lessonPlanName,
        );
        await expect(page.getByTestId(`course-outline-assessment-title-${projectName}`)).toContainText(projectName);
        await expect(page.getByTestId(`course-outline-assessment-title-${dueWorkName}`)).not.toBeVisible();

        tester.isGoingTo("check the number of students for each activity type imported");
        await expect(page.getByTestId(`course-outline-number-students-Pre-unit Quiz`)).toContainText(
            "0/4 students completed",
        );
        await expect(page.getByTestId(`course-outline-number-students-Post to Forum: Prologue`)).toContainText(
            "0/4 students completed",
        );
        await expect(page.getByTestId(`course-outline-number-students-Essay - Final Assessment`)).toContainText(
            "0/4 students completed",
        );
        await expect(page.getByTestId(`course-outline-number-students-${lessonPlanName}`)).not.toContainText(
            "0/4 students completed",
        );
        await expect(page.getByTestId(`course-outline-number-students-${projectName}`)).toContainText(
            "0/4 students completed",
        );

        tester.isGoingTo("check the due dates for each activity type imported");
        const dueDateToBeFormated = dayjs().add(+30, "days");
        const dateFormatted = dueDateToBeFormated.format("MMM D, YYYY");
        await expect(page.getByTestId(`course-outline-number-students-Pre-unit Quiz`)).toContainText(
            `Due ${dateFormatted}`,
        );
        await expect(page.getByTestId(`course-outline-number-students-Post to Forum: Prologue`)).toContainText(
            `Due ${dateFormatted}`,
        );
        await expect(page.getByTestId(`course-outline-number-students-Essay - Final Assessment`)).toContainText(
            `Due ${dateFormatted}`,
        );
        await expect(page.getByTestId(`course-outline-number-students-${lessonPlanName}`)).toContainText(
            `Scheduled ${dateFormatted}`,
        );
        await expect(page.getByTestId(`course-outline-number-students-${projectName}`)).toContainText(
            `Due ${dateFormatted}`,
        );
    });

    /**
     * Copy activity into a class
     * - navigate to /course/modify/6
     * - copy activity into a class for the unticked quiz, due work and quiz above
     * - copy the lesson plan and project into the class with new due dates
     * - fill the dates fields and click export to selected class
     * - ensure the assessment is presented in that class with the proper new dates
     */
    test("Copy activity into class", async ({ tester, page, db }) => {
        const courseModify = new CourseModify(page, tester);
        const importCourseIntoClass = new ImportCourseIntoClass(page);
        const lessonPlanName = "Export Activity Lesson Plan for Romeo and Juliet";
        const projectName = "Export Activity Project for Romeo and Juliet";

        const folderId = (await db.grabFromDatabase("folder", "id", {
            name: "Automation Course Import Folder1",
            deleted_at: null,
        })) as string;

        const projectId = (await db.grabFromDatabase("assessment", "id", {
            deleted_at: null,
            "title like": lessonPlanName,
        })) as number;

        const lessonPlanId = (await db.grabFromDatabase("assessment", "id", {
            deleted_at: null,
            "title like": projectName,
        })) as number;

        tester.isGoingTo("navigate to Year 9 English Modify course page");
        await courseModify.goto(6);

        tester.isGoingTo("click the arrow to expand Romeo and Juliet unit");
        await courseModify.clickUnitArrow(15);

        tester.isGoingTo("click the export icon for Romeo and Juliet Key Quote Activity");
        await courseModify.clickExportIcon(52);

        tester.isGoingTo("tick the checkbox");
        const courseImplementationId = (await db.grabFromDatabase("course_implementation", "id", {
            folder_id: folderId,
        })) as string;
        const importId = (await db.grabFromDatabase("course_unit_implementation", "id", {
            course_implementation_id: courseImplementationId,
        })) as string;
        await page.locator(`label[for=import-${importId}]`).dispatchEvent("click"); // Must dispatch as click() can't directly target the ::before pseudo-element

        tester.isGoingTo("click export the selected classes button");
        await page.click("input[type=submit]");

        tester.isGoingTo("click the export icon for Present a scene of Romeo and Juliet");
        await courseModify.clickExportIcon(146);

        tester.isGoingTo("tick the checkbox");
        await page.locator(`label[for=import-${importId}]`).dispatchEvent("click"); // Must dispatch as click() can't directly target the ::before pseudo-element

        tester.isGoingTo("click export the selected classes button");
        await page.click("input[type=submit]");

        tester.isGoingTo("click the export icon for Post-Unit Quiz");
        await courseModify.clickExportIcon(151);

        tester.isGoingTo("tick the checkbox");
        await page.locator(`label[for=import-${importId}]`).dispatchEvent("click"); // Must dispatch as click() can't directly target the ::before pseudo-element

        tester.isGoingTo("click export the selected classes button");
        await page.click("input[type=submit]");

        tester.isGoingTo("click the export icon for Lesson Plan for Romeo and Juliet");
        await courseModify.clickExportIcon(lessonPlanId);

        tester.isGoingTo("tick the checkbox");
        await page.locator(`label[for=import-${importId}]`).dispatchEvent("click"); // Must dispatch as click() can't directly target the ::before pseudo-element
        await page.locator("#confirm-dialog-confirm-button").click();
        await expect(page.locator("#confirm-dialog-confirm-button")).not.toBeVisible();
        tester.isGoingTo("set a new due date for the lesson plan");
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 10); // 10 days from now
        await importCourseIntoClass.insertExportDueDate(2, dueDate);

        tester.isGoingTo("click export the selected classes button");
        await page.click("input[type=submit]");

        tester.isGoingTo("click the export icon for Project for Romeo and Juliet");
        await courseModify.clickExportIcon(projectId);

        tester.isGoingTo("tick the checkbox");
        await page.locator(`label[for=import-${importId}]`).dispatchEvent("click"); // Must dispatch as click() can't directly target the ::before pseudo-element
        await page.locator("#confirm-dialog-confirm-button").click();
        await expect(page.locator("#confirm-dialog-confirm-button")).not.toBeVisible();

        tester.isGoingTo("set a new due date for the Project");
        await importCourseIntoClass.insertExportDueDate(2, dueDate);

        tester.isGoingTo("click export the selected classes button");
        await page.click("input[type=submit]");

        tester.isGoingTo(
            "navigate to the class homepage to check if the assessments are presented in the course outline",
        );
        await page.goto(`/homepage/${folderId}`);

        await expect(page.locator("#container")).toContainText("Romeo and Juliet Key Quote Activity"); // TODO: Use data-test
        await expect(page.locator("#container")).toContainText("Present a scene of Romeo and Juliet"); // TODO: Use data-test
        await expect(page.locator("#container")).toContainText("Post-Unit Quiz"); // TODO: Use data-test

        tester.isGoingTo("as Jack Coles check the course outline: dates, number of students");
        await tester.impersonate("Jack Coles");
        await page.goto(`/homepage/${folderId.toString()}`);
        await expect(page.getByTestId(`course-outline-number-students-${lessonPlanName}`)).not.toContainText(
            "0/4 students completed",
        );
        await expect(page.getByTestId(`course-outline-number-students-${projectName}`)).toContainText(
            "0/4 students completed",
        );
        tester.isGoingTo("check the due dates for each activity type imported");
        const dueDateToBeFormated = dayjs().add(+10, "days");
        const dateFormatted = dueDateToBeFormated.format("MMM D, YYYY");
        await expect(page.getByTestId(`course-outline-number-students-${lessonPlanName}`)).toContainText(
            `Scheduled ${dateFormatted}`,
        );
        await expect(page.getByTestId(`course-outline-number-students-${projectName}`)).toContainText(
            `Due ${dateFormatted}`,
        );
    });

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });

    test.afterAll(async ({ db, api }) => {
        // Soft-delete data to not interfere with other tests
        await api.deleteFoldersByName(db, "Automation Course Import Folder%");
        await db.updateInDatabase(
            "assessment",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Export Activity%", deleted_at: null },
        );
    });
});

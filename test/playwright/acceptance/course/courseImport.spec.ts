/**
 * This test imports the course into a class, copies an activity into a class
 */
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import ImportCourseIntoClass from "@schoolboxTest/playwright/support/page/courses/ImportCourseIntoClass";
import CreateLessonPlan from "@schoolboxTest/playwright/support/page/lessonPlan/CreateLessonPlan";
import TemplateList from "@schoolboxTest/playwright/support/page/lessonPlan/TemplateList";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import ManageGroup from "@schoolboxTest/playwright/support/page/group/ManageGroup";
import dayjs from "dayjs";
import CourseOutline from "@schoolboxTest/playwright/support/page/homepage/CourseOutline";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import EditorTemplate from "@schoolboxTest/playwright/support/page/admin/design/EditorTemplates";
import UnitPlanner from "@schoolboxTest/playwright/support/page/unitPlanner/UnitPlanner";

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
        const importCourseIntoClass = new ImportCourseIntoClass(page);
        const createLessonPlan = new CreateLessonPlan(tester, page);
        const templateList = new TemplateList(tester, page);
        const assessmentForm = new AssessmentForm(tester, page);
        const manageGroup = new ManageGroup(page);
        const editorTemplate = new EditorTemplate(page, tester);
        const unitPlanner = new UnitPlanner(tester, page);
        const courseOutline = new CourseOutline(tester, page);

        const folderName = `Automation Course Import Folder1`;
        const homepageTemplateOption = "Course";
        const schoolCode = "test1";
        const lessonPlanName = "Export Activity Lesson Plan for Romeo and Juliet";
        const projectName = "Export Activity Project for Romeo and Juliet";
        const dueWorkName = "Export Activity Test Due Work Project";

        // create a editor template to use in the due work
        tester.isGoingTo("Create a editor template to use in the due work");
        await editorTemplate.gotoCreateTemplatePage(6);
        await editorTemplate.createTemplate(
            "Learning Activity Automation Template",
            "This is an automation template",
            "This is a learning activity description.",
        );

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

        tester.isGoingTo("via unit planner, add add a lesson plan and project with a task under it");
        await unitPlanner.goto(6); // Navigate to Year 9 English unin planner page
        await unitPlanner.clickCreateActivityButton(15); //  Click Create Activity button for Romeo and Juliet unit
        await unitPlanner.clickCreateActivityOption("lessonPlan", 15);
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
        await unitPlanner.goto(6); // Navigate to Year 9 English unin planner page
        await unitPlanner.clickCreateActivityButton(15); //  Click Create Activity button for Romeo and Juliet unit
        await unitPlanner.clickCreateActivityOption("project", 15);
        await page.locator("input#title").fill(projectName);
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.clickCreate();
        await page.getByTestId("project-create-activity-link").click();
        await page.locator(`label[for="due-work"]`).click();
        await assessmentForm.setTitle(dueWorkName);
        // adding a description using a editor template to ensure it works via specification
        await tester.clickEditorTemplateCKEditor("assessment-description", "Learning Activity Automation Template");
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.clickCreate();
        await expect(page.locator("#assessment-list")).toBeVisible();
        const dueworkId = (await db.grabFromDatabase("assessment", "id", {
            deleted_at: null,
            "title LIKE": dueWorkName,
        })) as number;
        await page.getByTestId(`assessment-list-item-${dueworkId}`).click();
        await expect(page.locator("#ir-content")).toContainText("This is a learning activity description.");

        tester.isGoingTo(
            "navigate to the unit planner and click the option Import into classes from the dropdown menu",
        );
        await unitPlanner.goto(6);
        await unitPlanner.clickUnitPlannerDotMenu();
        await unitPlanner.clickImportIntoClass(6);

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
        await courseOutline.checkActivityMetaText([
            { activityName: "Pre-unit Quiz", expectMetaText: "0/4 students completed" },
            { activityName: "Post to Forum: Prologue", expectMetaText: "0/4 students completed" },
            { activityName: "Essay - Final Assessment", expectMetaText: "0/4 students completed" },
            { activityName: lessonPlanName, expectMetaText: "0/4 students completed", isMetaTextPresented: false },
            { activityName: projectName, expectMetaText: "0/4 students completed" },
        ]);

        tester.isGoingTo("check the due dates for each activity type imported");
        const dueDateToBeFormated = dayjs().add(+30, "days");
        const dateFormatted = dueDateToBeFormated.format("MMM D, YYYY");
        await courseOutline.checkActivityMetaText([
            { activityName: "Pre-unit Quiz", expectMetaText: `Due ${dateFormatted}` },
            { activityName: "Post to Forum: Prologue", expectMetaText: `Due ${dateFormatted}` },
            { activityName: "Essay - Final Assessment", expectMetaText: `Due ${dateFormatted}` },
            { activityName: lessonPlanName, expectMetaText: `Scheduled ${dateFormatted}` },
            { activityName: projectName, expectMetaText: `Due ${dateFormatted}` },
        ]);
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
        const importCourseIntoClass = new ImportCourseIntoClass(page);
        const lessonPlanName = "Export Activity Lesson Plan for Romeo and Juliet";
        const projectName = "Export Activity Project for Romeo and Juliet";
        const unitPlanner = new UnitPlanner(tester, page);
        const courseOutline = new CourseOutline(tester, page);

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

        tester.isGoingTo("navigate to Year 9 English Unit planner page and click view more for Romeo and Juliet unit");
        await unitPlanner.goto(6);
        await unitPlanner.clickViewMoreButton(15, "activities");

        tester.isGoingTo("click the 3-dot menu for Romeo and Juliet unit");
        await unitPlanner.clickActivityDotMenu(52);

        tester.isGoingTo("click the export for Romeo and Juliet Key Quote Activity");
        await unitPlanner.clickExportToClass(52);

        tester.isGoingTo("tick the checkbox");
        const courseImplementationId = (await db.grabFromDatabase("course_implementation", "id", {
            folder_id: folderId,
        })) as string;
        const importId = (await db.grabFromDatabase("course_unit_implementation", "id", {
            course_implementation_id: courseImplementationId,
        })) as string;

        await page.locator(`label[for=import-${importId}]`).click();

        tester.isGoingTo("click export the selected classes button");
        await page.click("input[type=submit]");

        tester.isGoingTo("click the export icon for Present a scene of Romeo and Juliet");
        await unitPlanner.clickViewMoreButton(15, "activities");
        await unitPlanner.clickActivityDotMenu(146);
        await unitPlanner.clickExportToClass(146);

        tester.isGoingTo("tick the checkbox");
        await page.locator(`label[for=import-${importId}]`).click();

        tester.isGoingTo("click export the selected classes button");
        await page.click("input[type=submit]");

        tester.isGoingTo("click the export icon for Post-Unit Quiz");
        await unitPlanner.clickViewMoreButton(15, "activities");
        await unitPlanner.clickActivityDotMenu(151);
        await unitPlanner.clickExportToClass(151);

        tester.isGoingTo("tick the checkbox");
        await page.locator(`label[for=import-${importId}]`).click();

        tester.isGoingTo("click export the selected classes button");
        await page.click("input[type=submit]");

        tester.isGoingTo("click the export icon for Lesson Plan for Romeo and Juliet");
        await unitPlanner.clickViewMoreButton(15, "activities");
        await unitPlanner.clickActivityDotMenu(lessonPlanId);
        await unitPlanner.clickExportToClass(lessonPlanId);

        tester.isGoingTo("tick the checkbox");
        await page.locator(`label[for=import-${importId}]`).click();

        await page.locator("#confirm-dialog-confirm-button").click();
        await expect(page.locator("#confirm-dialog-confirm-button")).not.toBeVisible();
        tester.isGoingTo("set a new due date for the lesson plan");
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 10); // 10 days from now
        await importCourseIntoClass.insertExportDueDate(2, dueDate);

        tester.isGoingTo("click export the selected classes button");
        await page.click("input[type=submit]");

        tester.isGoingTo("click the export icon for Project for Romeo and Juliet");
        await unitPlanner.clickViewMoreButton(15, "activities");
        await unitPlanner.clickActivityDotMenu(projectId);
        await unitPlanner.clickExportToClass(projectId);

        tester.isGoingTo("tick the checkbox");
        await page.locator(`label[for=import-${importId}]`).click();

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
        await courseOutline.checkActivityMetaText([
            { activityName: lessonPlanName, expectMetaText: "0/4 students completed", isMetaTextPresented: false },
            { activityName: projectName, expectMetaText: "0/4 students completed" },
        ]);

        tester.isGoingTo("check the due dates for each activity type imported");
        const dueDateToBeFormated = dayjs().add(+10, "days");
        const dateFormatted = dueDateToBeFormated.format("MMM D, YYYY");
        await courseOutline.checkActivityMetaText([
            { activityName: lessonPlanName, expectMetaText: `Scheduled ${dateFormatted}` },
            { activityName: projectName, expectMetaText: `Due ${dateFormatted}` },
        ]);

        tester.isGoingTo(
            "as Jana Reed check they are able to view activities under the lesson plan and mark the tasks",
        );
        await tester.impersonate("Jana Reed");
        await page.goto(`/homepage/${folderId.toString()}`);
        await page.getByTestId(`course-outline-assessment-title-${lessonPlanName}`).click();
        await page.locator("#linked-activities-container").getByText("Romeo and Juliet Key Quote Activity").isVisible();
        await page.locator("#linked-activities-container").getByText("Present a scene of Romeo and Juliet").isVisible();
        await page.locator('a[title="Incomplete"]').click();
        await expect(page.locator('a[title="Self-assessed"]')).toContainClass("progress-tick pending");

        tester.isGoingTo("as Jana Reed check they are able to view activities under the project");
        await page.goto(`/homepage/${folderId.toString()}`);
        await page.getByTestId(`course-outline-assessment-title-${projectName}`).click();
        await page
            .locator("#linked-activities-container")
            .getByText("Export Activity Test Due Work Project")
            .isVisible();
        tester.isGoingTo("as Jana Reed check the activities in the course and able to mark a task");
        await page.goto(`/homepage/${folderId.toString()}`);
        await expect(page.getByTestId(`course-outline-assessment-title-${projectName}`)).toContainText(projectName);
        await expect(page.getByTestId(`course-outline-assessment-title-${lessonPlanName}`)).toContainText(
            lessonPlanName,
        );
        await expect(
            page.getByTestId(`course-outline-assessment-title-Romeo and Juliet Key Quote Activity`),
        ).toContainText("Romeo and Juliet Key Quote Activity");
        await expect(page.getByTestId(`course-outline-assessment-title-Essay - Final Assessment`)).toContainText(
            "Essay - Final Assessment",
        );
        await page.getByTestId(`tick-Key events sequencing task`).click();

        tester.isGoingTo("as jack coles check the course outline shows 1/4 students completed the activity");
        await tester.impersonate("Jack Coles");
        await page.goto(`/homepage/${folderId.toString()}`);
        await courseOutline.checkActivityMetaText([
            { activityName: "Romeo and Juliet Key Quote Activity", expectMetaText: "1/4 students completed" },
            { activityName: "Key events sequencing task", expectMetaText: "1/4 students completed" },
        ]);
    });

    test("Create activities via course outline, save into the course, delete activities, check students cannot view activities with open date in the future", async ({
        tester,
        page,
        db,
    }) => {
        const assessmentForm = new AssessmentForm(tester, page);
        const courseOutline = new CourseOutline(tester, page);
        const unitName = "Romeo and Juliet";
        const dueWorkName = `Due Work via Course Outline`;
        const taskName = `Task via Course Outline`;

        const folderId = (await db.grabFromDatabase("folder", "id", {
            name: "Automation Course Import Folder1",
            deleted_at: null,
        })) as string;

        await tester.impersonate("Jack Coles");

        tester.isGoingTo("navigate to the class homepage to create a due work and a task via course outline");
        await page.goto(`/homepage/${folderId}`);

        await courseOutline.selectOptionUnitDotMenu(unitName, "dueWork");

        tester.isGoingTo("Create a due work with open date in the future");
        const dueWorkOpenDate = new Date();
        dueWorkOpenDate.setDate(dueWorkOpenDate.getDate() + 5); // 5 days from now
        const dueWorkDueDate = new Date();
        dueWorkDueDate.setDate(dueWorkDueDate.getDate() + 10); // 10 days from now
        await assessmentForm.setTitle(dueWorkName);
        await assessmentForm.setDueDate(dueWorkDueDate);
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.setPublishDate(dueWorkOpenDate);
        await assessmentForm.create();
        const dueworkId = (await db.grabFromDatabase("assessment", "id", {
            deleted_at: null,
            "title LIKE": dueWorkName,
        })) as number;

        tester.isGoingTo("Create a task");

        await courseOutline.selectOptionUnitDotMenu(unitName, "task");
        await assessmentForm.setTitle(taskName);
        await tester.clickEditorTemplateCKEditor("assessment-description", "Learning Activity Automation Template");
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.create();
        const taskId = (await db.grabFromDatabase("assessment", "id", {
            deleted_at: null,
            "title LIKE": taskName,
        })) as number;

        tester.isGoingTo("as Jack Coles, check the due work and task are presented in the course outline");
        await expect(page.getByTestId(`course-outline-assessment-title-${dueWorkName}`)).toContainText(dueWorkName);
        await expect(page.getByTestId(`course-outline-assessment-title-${taskName}`)).toContainText(taskName);

        tester.isGoingTo("save the due work and task into the course via course outline");
        await courseOutline.saveActivityToCourse(unitName, taskId, `save-course-${taskId}`);
        await courseOutline.saveActivityToCourse(unitName, dueworkId, `save-course-${dueworkId}`);

        tester.isGoingTo("check that the task has the template description");
        await page.getByTestId(`course-outline-assessment-title-${taskName}`).click();
        await expect(page.locator("article.content")).toContainText("This is a learning activity description.");

        tester.isGoingTo("navigate to Romeo and Juliet unit page and check the assessment are presented");
        await page.goto("/homepage/88");
        await expect(page.getByTestId(`course-builder-assessment-title-${dueWorkName}`)).toContainText(dueWorkName);
        await expect(page.getByTestId(`course-builder-assessment-title-${taskName}`)).toContainText(taskName);

        tester.isGoingTo("as Jana Reed, check the course outline is showing the task and hides the due work ");
        await tester.impersonate("Jana Reed");
        await page.goto(`/homepage/${folderId}`);
        await expect(page.getByTestId(`course-outline-assessment-title-${taskName}`)).toBeVisible();
        await expect(page.getByTestId(`course-outline-assessment-title-${dueWorkName}`)).not.toBeVisible();

        tester.isGoingTo("as Jack Coles, delete the due work and task created via course outline");
        await tester.impersonate("Jack Coles");
        await page.goto(`/homepage/${folderId}`);
        await courseOutline.deleteActivity(unitName, taskId, `delete-${taskId}`);
        await page.reload();
        await courseOutline.deleteActivity(unitName, dueworkId, `delete-${dueworkId}`);
        await expect(page.getByTestId(`course-outline-assessment-title-${taskName}`)).not.toBeVisible();
        await expect(page.getByTestId(`course-outline-assessment-title-${dueWorkName}`)).not.toBeVisible();
    });

    test("Course Outline: filters and 3-dot options", async ({ tester, page, db }) => {
        const homepage = new Homepage(tester, page);
        const courseOutline = new CourseOutline(tester, page);

        const dueWorkName = `Essay - Final Assessment`;
        const taskName = `Post to Forum: Prologue`;
        const projectName = "Export Activity Project for Romeo and Juliet";
        const lessonPlanName = "Export Activity Lesson Plan for Romeo and Juliet";
        const quizName = "Pre-unit Quiz";

        tester.isGoingTo(
            "Filter the course outline by project, quiz and lesson plans and do not show title to read-only users",
        );
        const folderId = (await db.grabFromDatabase("folder", "id", {
            name: "Automation Course Import Folder1",
            deleted_at: null,
        })) as string;
        await page.goto(`/homepage/${folderId}`);

        await homepage.clickLockToggle();
        const component = page.locator(Homepage.locateComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1));
        const componentId = (await component.getAttribute("id")) as string;
        await Homepage.clickComponentSettings(component);
        await courseOutline.filterByLabel(componentId, "dueWork");
        await courseOutline.filterByLabel(componentId, "task");
        await CourseOutline.clickReadOnlyUsers(component, componentId);
        await component.locator(`input[type="submit"]`).click();
        await expect(component.locator(`input[type="submit"]`)).toBeHidden();

        tester.isGoingTo("add another course outline component to the page and filter by due work and task");
        await page.reload();
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_LEARNING, "Course Outline");
        const componentCourseOutline = page.locator(Homepage.locateComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1));
        const componentCourseOutlineId = (await componentCourseOutline.getAttribute("id")) as string;
        await homepage.clickLockToggle();
        await Homepage.clickComponentSettings(componentCourseOutline);
        await homepage.typeTextBoxHeading(`Due Work and Tasks Only`);
        await courseOutline.filterByLabel(componentCourseOutlineId, "project");
        await courseOutline.filterByLabel(componentCourseOutlineId, "quiz");
        await courseOutline.filterByLabel(componentCourseOutlineId, "lessonPlan");
        await CourseOutline.selectImportedCourse(componentCourseOutline, "Year 9 English");
        await componentCourseOutline.locator(`input[type="submit"]`).click();

        tester.isGoingTo("check the filters are working correctly for the teacher and the title is showing for them");
        await tester.impersonate("Jack Coles");
        await page.goto(`/homepage/${folderId}`);
        await expect(componentCourseOutline.locator('a:has(sbx-icon[name="filter"])')).toContainText(
            "This is being filtered",
        );
        await expect(componentCourseOutline.locator('a:has(sbx-icon[name="course"])')).toContainText("Year 9 English");
        await expect(componentCourseOutline.getByTestId(`homepage-component-titlebar-name`)).toContainText(
            `Due Work and Tasks Only`,
        );
        await expect(componentCourseOutline.getByTestId(`course-outline-assessment-title-${taskName}`)).toBeVisible();
        await expect(
            componentCourseOutline.getByTestId(`course-outline-assessment-title-${dueWorkName}`),
        ).toBeVisible();
        await expect(
            componentCourseOutline.getByTestId(`course-outline-assessment-title-${quizName}`),
        ).not.toBeVisible();
        await expect(
            componentCourseOutline.getByTestId(`course-outline-assessment-title-${lessonPlanName}`),
        ).not.toBeVisible();
        await expect(
            componentCourseOutline.getByTestId(`course-outline-assessment-title-${projectName}`),
        ).not.toBeVisible();

        tester.isGoingTo("check Jack Coles can see the proper options under the unit 3-dot menu");
        await courseOutline.clickUnitDotMenu("Romeo and Juliet");
        await expect(componentCourseOutline.getByTestId("course-outline-menu-add-project")).toContainText("Project");
        await expect(componentCourseOutline.getByTestId("course-outline-menu-add-dueWork")).toContainText("Due Work");
        await expect(componentCourseOutline.getByTestId("course-outline-menu-add-quiz")).toContainText("Quiz");
        await expect(componentCourseOutline.getByTestId("course-outline-menu-add-task")).toContainText("Task");
        await expect(componentCourseOutline.getByTestId("course-outline-menu-add-lessonPlan")).toContainText(
            "Lesson Plan",
        );
        await expect(componentCourseOutline.getByTestId("course-outline-menu-course-markbook")).toContainText(
            "Course Markbook",
        );
        await expect(componentCourseOutline.getByTestId("course-outline-unit-delete")).toContainText("Delete");
        await courseOutline.clickUnitDotMenu("Romeo and Juliet");

        tester.isGoingTo("check Jack Coles can see the proper options in the 3-dot menu for each activity type");
        // task
        tester.isGoingTo("checking task");
        await courseOutline.clickActivityDotMenu("Due Work and Tasks Only", "Romeo and Juliet", taskName);

        const taskId = await courseOutline.getActivityId("Due Work and Tasks Only", "Romeo and Juliet", taskName);

        await expect(componentCourseOutline.getByTestId(`course-outline-view-status-${taskId}`)).toContainText(
            "View Status",
        );
        await expect(componentCourseOutline.getByTestId(`course-outline-custom-participation-${taskId}`)).toContainText(
            "Student Participation",
        );
        await expect(componentCourseOutline.getByTestId(`course-outline-modify-${taskId}`)).toContainText("Modify");
        await expect(componentCourseOutline.getByTestId(`activity-delete-${taskId}`)).toContainText("Delete");
        await expect(componentCourseOutline.getByTestId(`activity-save-course-${taskId}`)).toContainText(
            "Save to Course...",
        );
        await courseOutline.clickActivityDotMenu("Due Work and Tasks Only", "Romeo and Juliet", taskName);

        // due work
        tester.isGoingTo("checking due work");
        const dueWorkId = await courseOutline.getActivityId("Due Work and Tasks Only", "Romeo and Juliet", dueWorkName);

        await courseOutline.clickActivityDotMenu("Due Work and Tasks Only", "Romeo and Juliet", dueWorkName);
        await expect(componentCourseOutline.getByTestId(`course-outline-view-status-${dueWorkId}`)).toContainText(
            "View Status",
        );
        await expect(
            componentCourseOutline.getByTestId(`course-outline-view-grade-submissions-${dueWorkId}`),
        ).toContainText("View and grade submissions");
        await expect(
            componentCourseOutline.getByTestId(`course-outline-custom-participation-${dueWorkId}`),
        ).toContainText("Student Participation");
        await expect(componentCourseOutline.getByTestId(`course-outline-similarity-report-${dueWorkId}`)).toContainText(
            "Similarity Report",
        );
        await expect(componentCourseOutline.getByTestId(`course-outline-rubric-report-${dueWorkId}`)).toContainText(
            "Rubric Report",
        );
        await expect(componentCourseOutline.getByTestId(`course-outline-modify-${dueWorkId}`)).toContainText("Modify");
        await expect(componentCourseOutline.getByTestId(`activity-delete-${dueWorkId}`)).toContainText("Delete");
        await expect(componentCourseOutline.getByTestId(`activity-save-course-${dueWorkId}`)).toContainText(
            "Save to Course...",
        );
        await courseOutline.clickActivityDotMenu("Due Work and Tasks Only", "Romeo and Juliet", dueWorkName);

        const componentBottom = page.locator(Homepage.locateComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 2));

        // project
        tester.isGoingTo("checking project");
        const projectId = await courseOutline.getActivityId("Course Outline", "Romeo and Juliet", projectName);

        await courseOutline.clickActivityDotMenu("Course Outline", "Romeo and Juliet", projectName);
        await expect(componentBottom.getByTestId(`course-outline-view-status-${projectId}`)).toContainText(
            "View Status",
        );
        await expect(componentBottom.getByTestId(`course-outline-modify-${projectId}`)).toContainText("Modify");
        await expect(componentBottom.getByTestId(`activity-delete-${projectId}`)).toContainText("Delete");
        await expect(componentBottom.getByTestId(`activity-save-course-${projectId}`)).toContainText(
            "Save to Course...",
        );
        await courseOutline.clickActivityDotMenu("Course Outline", "Romeo and Juliet", projectName);

        // lesson plan
        tester.isGoingTo("checking lesson plan");
        const lessonPlanId = await courseOutline.getActivityId("Course Outline", "Romeo and Juliet", lessonPlanName);

        await courseOutline.clickActivityDotMenu("Course Outline", "Romeo and Juliet", lessonPlanName);
        await expect(componentBottom.getByTestId(`course-outline-modify-${lessonPlanId}`)).toContainText("Modify");
        await expect(componentBottom.getByTestId(`activity-delete-${lessonPlanId}`)).toContainText("Delete");
        await expect(componentBottom.getByTestId(`activity-save-course-${lessonPlanId}`)).toContainText(
            "Save to Course...",
        );
        await courseOutline.clickActivityDotMenu("Course Outline", "Romeo and Juliet", lessonPlanName);

        // quiz
        tester.isGoingTo("checking quiz");
        const quizId = await courseOutline.getActivityId("Course Outline", "Romeo and Juliet", quizName);

        await courseOutline.clickActivityDotMenu("Course Outline", "Romeo and Juliet", quizName);
        await expect(componentBottom.getByTestId(`course-outline-view-status-${quizId}`)).toContainText("View Status");
        await expect(componentBottom.getByTestId(`course-outline-custom-participation-${quizId}`)).toContainText(
            "Student Participation",
        );
        await expect(componentBottom.getByTestId(`course-outline-horizontal-${quizId}`)).toContainText(
            "Review horizontally",
        );
        await expect(componentBottom.getByTestId(`course-outline-modify-${quizId}`)).toContainText("Modify Settings");
        await expect(componentBottom.getByTestId(`course-outline-modify-contents-${quizId}`)).toContainText(
            "Modify Contents",
        );
        await expect(componentBottom.getByTestId(`activity-delete-${quizId}`)).toContainText("Delete");
        await expect(componentBottom.getByTestId(`activity-save-course-${quizId}`)).toContainText("Save to Course...");
        await courseOutline.clickActivityDotMenu("Course Outline", "Romeo and Juliet", quizName);

        tester.isGoingTo("check the filters are working correctly for the student and the title is hidden for them");

        await tester.impersonate("Jana Reed");
        await page.goto(`/homepage/${folderId}`);
        await expect(componentCourseOutline.getByTestId("homepage-component-titlebar-name")).toContainText(
            "Due Work and Tasks Only",
        );
        await expect(componentCourseOutline.locator('a:has(sbx-icon[name="filter"])')).not.toBeVisible();
        await expect(componentCourseOutline.locator('a:has(sbx-icon[name="course"])')).toContainText("Year 9 English");
        await expect(componentCourseOutline.getByTestId(`homepage-component-titlebar-name`)).toContainText(
            `Due Work and Tasks Only`,
        );
        await expect(componentBottom.getByTestId("homepage-component-titlebar-name")).not.toBeVisible();

        await expect(componentBottom.getByTestId(`course-outline-assessment-title-${dueWorkName}`)).not.toBeVisible();
        await expect(componentBottom.getByTestId(`course-outline-assessment-title-${taskName}`)).not.toBeVisible();
        await expect(componentBottom.getByTestId(`course-outline-assessment-title-${quizName}`)).toBeVisible();
        await expect(componentBottom.getByTestId(`course-outline-assessment-title-${lessonPlanName}`)).toBeVisible();
        await expect(componentBottom.getByTestId(`course-outline-assessment-title-${projectName}`)).toBeVisible();

        tester.isGoingTo("check Jana Reed is redirected to the activity page when clicking on an activity title");
        await componentCourseOutline.getByTestId(`course-outline-assessment-title-${taskName}`).click();
        await expect(page).toHaveURL(`learning/assessments/${taskId}/25`);
        await page.goBack();
        await componentCourseOutline.getByTestId(`course-outline-assessment-title-${dueWorkName}`).click();
        await expect(page).toHaveURL(`learning/assessments/${dueWorkId}/25`);
        await page.goBack();
        await componentBottom.getByTestId(`course-outline-assessment-title-${projectName}`).click();
        await expect(page).toHaveURL(`learning/assessments/${projectId}/25`);
        await page.goBack();
        await componentBottom.getByTestId(`course-outline-assessment-title-${lessonPlanName}`).click();
        await expect(page).toHaveURL(`learning/assessments/${lessonPlanId}/25`);
        await page.goBack();
        await componentBottom.getByTestId(`course-outline-assessment-title-${quizName}`).click();
        await expect(page).toHaveURL(`learning/assessments/${quizId}/25`);
        await page.goBack();
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
        await db.updateInDatabase(
            "assessment",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "%Course Outline%", deleted_at: null },
        );
        await db.updateInDatabase(
            "editor_template",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Learning Activity Automation Template%", deleted_at: null },
        );
    });
});

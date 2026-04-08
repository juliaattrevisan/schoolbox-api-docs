import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CourseBuilder from "@schoolboxTest/playwright/support/page/homepage/CourseBuilder";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import CreateLessonPlan from "@schoolboxTest/playwright/support/page/lessonPlan/CreateLessonPlan";
import UnitPlanner from "@schoolboxTest/playwright/support/page/unitPlanner/UnitPlanner";

/**
 * Tests for the Course Builder homepage component
 * Tests cover:
 * - empty message when there are no activities yet
 * - adding an activity and verifying it appears in the component
 * - checking the activities 3-dot menu options are presented for:
 *   - due work, task, quiz, project and lesson plans
 * - check the breadcrumb is properly presented in the component
 * - check the options to export and modify an activity redirects to the proper page
 * - sort the activities and check it sorts correctly in the component and unit planner
 * - delete an activity and check it is removed from the component and unit planner
 * - check the student cannot see the course builder component
 */

test.describe.serial("Homepage - Course Builder Component", () => {
    let courseBuilder: CourseBuilder;
    let homepage: Homepage;
    let assessmentForm: AssessmentForm;
    let createLessonPlan: CreateLessonPlan;
    let unitPlanner: UnitPlanner;
    const COMPONENT_NAME = "Course Builder";
    const MATH_HOMEPAGE_ID = 145;
    const MATH_UNIT_SPECIFICATION_ID = 31;
    const ROMEO_AND_JULIET_HOMEPAGE_ID = 88;
    const LESSON_PLAN_TITLE = "Course Builder Lesson Plan";
    const LESSON_PLAN_TEMPLATE_ID = 3;

    test.beforeEach(async ({ tester, page }) => {
        courseBuilder = new CourseBuilder(tester, page);
        homepage = new Homepage(tester, page);
        assessmentForm = new AssessmentForm(tester, page);
        createLessonPlan = new CreateLessonPlan(tester, page);
        unitPlanner = new UnitPlanner(tester, page);
        await tester.loginAsAdmin();
    });

    test("Check the unit page course builder empty message, create an activity under it and check the 3-dot options for the activity", async ({
        tester,
        page,
        db,
    }) => {
        try {
            tester.isGoingTo("navigate to homepage to check the course builder component empty message");
            await homepage.goto(MATH_HOMEPAGE_ID);
            tester.isGoingTo("check the empty message is shown in the course builder component");
            await homepage.verifyComponentEmptyStateIsShown(COMPONENT_NAME);
            tester.isGoingTo("click the create activity link and create a project");
            await courseBuilder.clickCreateActivityButton(MATH_UNIT_SPECIFICATION_ID);
            await courseBuilder.clickActivityButton("project");
            await assessmentForm.setTitle("Course Builder Project");
            await assessmentForm.setWorkType(6);
            await assessmentForm.clickCreate();
            await expect(page.locator(`a:text-is("Return")`)).toBeVisible();
            tester.isGoingTo("check the created project is shown in the course builder component");
            const projectId = (await db.grabFromDatabase("assessment", "id", {
                title: "Course Builder Project",
                deleted_at: null,
            })) as number;
            await homepage.goto(MATH_HOMEPAGE_ID);
            await expect(page.getByTestId("course-builder-assessment-title-Course Builder Project")).toContainText(
                "Course Builder Project",
            );
            await courseBuilder.clickActivityMenuAndCheckOptions(projectId, "project");
        } finally {
            tester.isGoingTo("delete the created project via database to clean up");
            await db.updateInDatabase(
                "assessment",
                { deleted_at: "2022-01-15 11:22:34" },
                { "title like": "Course Builder Project%", deleted_at: null },
            );
        }
    });

    test("check the activity 3-dot menu options are presented for a quiz, task, due work and lesson plan; delete the lesson plan and check it is not presented in the component and unit planner", async ({
        tester,
        page,
        db,
    }) => {
        try {
            tester.isGoingTo("create a lesson plan to be used in the tests");
            await createLessonPlan.createLessonPlan(
                MATH_UNIT_SPECIFICATION_ID,
                LESSON_PLAN_TEMPLATE_ID,
                LESSON_PLAN_TITLE,
            );
            tester.isGoingTo(
                "check the 3-dot menu options are presented for a quiz, task, due work, lesson and project and check the course builder breadcrumb",
            );
            await homepage.goto(MATH_HOMEPAGE_ID);
            const lessonPlanId = (await db.grabFromDatabase("assessment", "id", {
                title: LESSON_PLAN_TITLE,
                deleted_at: null,
            })) as number;
            await courseBuilder.clickActivityMenuAndCheckOptions(lessonPlanId, "lesson-plan");
            await courseBuilder.checkBreadcrumb(["Courses", "Junior School", "Year 5"], [2, 134, 143]);
            tester.isGoingTo(
                "delete the created lesson plan and check it is deleted in the component and unit planner",
            );
            await courseBuilder.deleteActivity(lessonPlanId);
            await expect(page.getByTestId(`course-builder-assessment-title-${LESSON_PLAN_TITLE}`)).not.toBeVisible();
            await page.goto("/course/plan/6");
            await expect(page.getByTestId(`unit-planner-activity-title-${LESSON_PLAN_TITLE}`)).not.toBeVisible();

            await homepage.goto(ROMEO_AND_JULIET_HOMEPAGE_ID);
            await courseBuilder.clickActivityMenuAndCheckOptions(51, "quiz");
            await courseBuilder.clickActivityMenuAndCheckOptions(59, "task");
            await courseBuilder.clickActivityMenuAndCheckOptions(144, "due-work");
            await courseBuilder.checkBreadcrumb(["Courses", "English", "Year 9 English"], [2, 85, 87]);
        } finally {
            tester.isGoingTo("delete the created lesson plan via database to clean up");
            await db.updateInDatabase(
                "assessment",
                { deleted_at: "2022-01-15 11:22:34" },
                { "title like": `${LESSON_PLAN_TITLE}%`, deleted_at: null },
            );
        }
    });

    test("Re-sequence Activities", async ({ tester, page, db }) => {
        try {
            const quizId = 51;
            const taskId = 59;
            tester.isGoingTo("Get the current order of activities");
            await homepage.goto(ROMEO_AND_JULIET_HOMEPAGE_ID);
            const initialActivityOrder = await courseBuilder.getActivityOrder();

            tester.isGoingTo("Verify Pre-unit Quiz to be above Post to Forum: Prologue in the initial order");
            expect(initialActivityOrder.indexOf(quizId.toString())).toBeLessThan(
                initialActivityOrder.indexOf(taskId.toString()),
            );

            tester.isGoingTo("drag the task Post to Forum: Prologue above Pre-unit Quiz");
            await courseBuilder.dragAndDropActivity(taskId, quizId);
            const newActivityOrder = await courseBuilder.getActivityOrder();

            tester.isGoingTo(
                "verify that the activities have been correctly reordered in the course builder and unit planner",
            );
            expect(newActivityOrder.indexOf(taskId.toString())).toBeLessThan(
                newActivityOrder.indexOf(quizId.toString()),
            );

            await page.reload();

            const finalActivityOrder = await courseBuilder.getActivityOrder();
            expect(finalActivityOrder).toEqual(newActivityOrder);

            await page.goto("/course/plan/6");
            const newPlannerActivityOrder = await unitPlanner.getActivityOrder();
            expect(newPlannerActivityOrder.indexOf(taskId.toString())).toBeLessThan(
                newPlannerActivityOrder.indexOf(quizId.toString()),
            );
        } finally {
            tester.isGoingTo("update the activities order back to the original order");
            await db.updateInDatabase("assessment", { sequence: 1 }, { id: 51 });
            await db.updateInDatabase("assessment", { sequence: 2 }, { id: 59 });
        }
    });

    test("check the student cannot see the course builder component", async ({ tester}) => {
        tester.isGoingTo("login as a student and navigate to the homepage");
        await tester.impersonate("Jana Reed");
        await homepage.goto(ROMEO_AND_JULIET_HOMEPAGE_ID);
        tester.isGoingTo("check the course builder component is not visible for the student");
        await expect(homepage.locateComponentByName("Course Builder")).toHaveCount(0);
    });

    test("check that by clicking export and modify redirects to the proper page", async ({ tester, page }) => {
        const activityId = 59;
        tester.isGoingTo(
            "navigate to homepage, click modify for an activity and check it redirects to the activity modify page",
        );
        await homepage.goto(ROMEO_AND_JULIET_HOMEPAGE_ID);
        await Promise.all([
            page.waitForURL(`**/learning/assessments/${activityId}/modify**`),
            courseBuilder.modifyActivity(activityId),
        ]);
        await expect(page).toHaveURL(new RegExp(`/learning/assessments/${activityId}/modify`));
        tester.isGoingTo(
            "navigate to homepage, click export for an activity and check it redirects to the activity export page",
        );
        await homepage.goto(ROMEO_AND_JULIET_HOMEPAGE_ID);
        await Promise.all([
            page.waitForURL(`**/learning/export/assessment/${activityId}**`),
            courseBuilder.exportActivity(activityId),
        ]);
        await expect(page).toHaveURL(new RegExp(`/learning/export/assessment/${activityId}`));
    });
});

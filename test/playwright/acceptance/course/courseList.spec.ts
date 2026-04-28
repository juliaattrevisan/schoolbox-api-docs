import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CourseList from "@schoolboxTest/playwright/support/page/courses/CourseList";
import CourseModify from "@schoolboxTest/playwright/support/page/courses/CourseModify";
import Common from "@schoolboxTest/playwright/support/page/Common";
import UnitPlanner from "@schoolboxTest/playwright/support/page/unitPlanner/UnitPlanner";

test.describe("Course list tests", () => {
    const courseName = "Year 9 English";
    const courseId = 6; // year 9 english course id
    const courseYear3 = "Year 3";
    const courseYear9French = "Year 9 French";
    const newCourseName = "Automation Test Course";

    test.beforeEach(async ({ tester, page }) => {
        await tester.loginAsAdmin();
        const courseList = new CourseList(tester, page);
        await courseList.goto();
    });

    /**
     * navigate to course list page
     * search for a course that does not exist
     * check the empty message is shown
     * search for a course that does exist
     * ensure the course is shown in the results
     * click the course 3-dot menu and ensure the proper options are shown
     * check by expanding the arrow the units are presented
     */
    test("Searches for a course checking the results and the 3-dot options", async ({ tester, page }) => {
        const courseList = new CourseList(tester, page);
        const common = new Common(page);

        tester.isGoingTo("search for a course that does not exist and check the empty message is shown");
        await courseList.searchForCourse("Nonexistent Course");
        await common.verifyEmptyStateIsShown();
        await page.reload(); // Clear previous search

        tester.isGoingTo("search for an existing course and ensure the course is shown in the results");
        await courseList.searchForCourse("Year 9");
        await courseList.verifyCourseCount(2);
        await expect(page.getByTestId(`course-list-${courseYear3}`)).not.toBeVisible();
        await expect(page.getByTestId(`course-list-${courseYear9French}`)).toBeVisible();

        tester.isGoingTo("click the course 3-dot menu and ensure the proper options are shown");
        await courseList.verifyCourseDotMenuOptions(
            courseId,
            courseName,
            [
                "import",
                "homepage",
                "rubric",
                "markbook",
                "analytics",
                "calendar",
                "planner",
                "report",
                "modify",
                "delete",
            ],
            [],
        );

        tester.isGoingTo("expand the course to check the units are presented");
        await courseList.verifyCourseUnitsCount(courseName, courseId, 3);

        tester.isGoingTo("impersonate a teacher and check the course 3-dot menu options");
        await tester.impersonateTeacher();
        await courseList.goto();
        await courseList.verifyCourseDotMenuOptions(
            courseId,
            courseName,
            ["import", "homepage", "rubric", "markbook", "analytics", "calendar", "planner", "report", "modify"],
            ["delete"],
        );
    });

    /**
     * Tests that clicking the course 3-dot menu options redirects to the proper page
     */
    test("Clicking the course 3-dot menu options redirects to the proper page", async ({ tester, page }) => {
        const courseList = new CourseList(tester, page);

        tester.isGoingTo("click the course 3-dot menu options and check it redirects to the proper page");
        await courseList.checkCourseDotMenuOptionsRedirects(
            courseId,
            courseName,
            ["import", "homepage", "rubric", "markbook", "analytics", "calendar", "planner", "report", "modify"],
            [
                /.*\/course\/import\/6\/folder\/0\/0*/,
                /.*\/homepage\/87\/$/,
                /.*\/rubric\/manager\/87$/,
                /.*\/learning\/courseMarkbook\/6*/,
                /.*\/learning\/analytics\/course\/6*/,
                /.*\/learning\/assessment\/calendar*/,
                /.*\/course\/plan\/6$/,
                /.*\/curriculum\/reports\/coverage\?courseId=6$/,
                /.*\/course\/modify\/6$/,
            ],
        );
    });

    /**
     * Adds a new course and ensures it appears in the course list
     * Deletes the newly created course and ensures it is removed from the course list
     */
    test("Adds and deletes a course from the course list", async ({ tester, page, db }) => {
        try {
            const courseList = new CourseList(tester, page);
            const courseModify = new CourseModify(page, tester);
            const common = new Common(page);

            tester.isGoingTo("add a new course and check the name and faculty are mandatory fields");
            await courseList.clickAddCourse();
            await courseModify.checkMandatoryErrorMessages();

            tester.isGoingTo("add a new course and ensure it appears in the course list");
            await courseModify.clickSelectLocation();
            await courseModify.clickFolderOnSelectFolderModal(85); // English folder
            await courseModify.clickSelectUseFolderLocation(85);
            await courseModify.fillCourseName(newCourseName);
            await courseModify.clickNextButton();
            await expect(page).toHaveURL(/.*\/course\/plan*/);
            await courseList.goto();
            await courseList.verifyCourseNameInList(newCourseName);

            const folderId = (await db.grabFromDatabase("folder", "id", {
                name: newCourseName,
            })) as number;
            const courseSpecId = (await db.grabFromDatabase("course_specification", "id", {
                folder_id: folderId,
            })) as number;

            tester.isGoingTo("delete the newly created course and ensure it is removed from the course list");
            await courseList.clickCourseDotMenuOption(courseSpecId, newCourseName, "delete");
            await common.clickConfirmButton();
            await courseList.verifyCourseNameInList(newCourseName, false);
        } finally {
            tester.isGoingTo("clean up the database by removing the created course and folder");
            await UnitPlanner.dbDeleteCourse(db, newCourseName);
        }
    });
});

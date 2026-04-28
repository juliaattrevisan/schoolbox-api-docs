import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@schoolboxTest/playwright/support/integrations";

export default class CourseList {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/course/list";

    static readonly DATA_TEST_COURSE_DOT_MENU = "sbx-pop-out-courseDropdown"; // Append "-[courseDropdownNumber]"

    static readonly SEARCH_FIELD = "#course-search";

    static readonly DATA_TEST_ADD_BUTTON = "course-list-icon-add";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to course list page
     */
    async goto(): Promise<void> {
        await this.page.goto(CourseList.URL);
        await this.tester.common.waitForPageLoad();
    }

    /**
     * Searches for a course
     * @param courseName - The course name to search for
     */
    async searchForCourse(courseName: string): Promise<void> {
        await this.page.fill(CourseList.SEARCH_FIELD, courseName);
        await this.page.keyboard.press("Enter");
    }

    /**
     * click add button to create a new course
     */
    async clickAddCourse(): Promise<void> {
        await this.page.getByTestId(CourseList.DATA_TEST_ADD_BUTTON).click();
        await this.tester.common.waitForPageLoad();
    }

    /**
     * Clicks the 3-dot menu for a course and check whether the list of array options are presented or not
     * @param courseDropdownNumber - The course dropdown number to click
     * @param courseName - The course name to verify options for
     * @param optionsToBePresent - The list of options that should be present: "import", "homepage", "rubric", "markbook", "analytics", "calendar", "planner", "report", "modify", "delete",
     * @param optionsNotToBePresent - The list of options that should not be present
     */
    async verifyCourseDotMenuOptions(
        courseDropdownNumber: number,
        courseName: string,
        optionsToBePresent: string[],
        optionsNotToBePresent: string[],
    ): Promise<void> {
        await this.clickCourseDotMenu(courseDropdownNumber);
        for (const option of optionsToBePresent) {
            await expect(this.page.getByTestId(`course-list-${option}-${courseName}`)).toBeVisible();
        }
        for (const option of optionsNotToBePresent) {
            await expect(this.page.getByTestId(`course-list-${option}-${courseName}`)).not.toBeVisible();
        }
    }

    /**
     * Finds the course list item and checks how many courses are shown
     * @param expectedCount - The expected count of courses shown
     */
    async verifyCourseCount(expectedCount: number): Promise<void> {
        const courseList = this.page.locator("ul.information-list");
        await expect(courseList.locator(`li[data-course-row]`)).toHaveCount(expectedCount);
    }

    /**
     * Clicks a course option in the 3-dot menu for each options passed in the array, checks it redirects to the proper page url and navigate back to the course
     * @param courseDropdownNumber - The course dropdown number to click
     * @param courseName - The course name to verify options for
     * @param optionsToClick - The list of options to click: "import", "homepage", "rubric", "markbook", "analytics", "calendar", "planner", "report", "modify", "delete",
     * @param expectedUrl - The expected URL after clicking each option
     */
    async checkCourseDotMenuOptionsRedirects(
        courseDropdownNumber: number,
        courseName: string,
        optionsToClick: string[],
        expectedUrl: RegExp[],
    ): Promise<void> {
        for (const [i, option] of optionsToClick.entries()) {
            const expectedOptionUrl = expectedUrl[i];
            await this.clickCourseDotMenu(courseDropdownNumber);
            await this.page.getByTestId(`course-list-${option}-${courseName}`).click();
            await expect(this.page).toHaveURL(expectedOptionUrl);
            await this.goto();
        }
    }

    /**
     * Clicks an option in the 3-dot menu for a course
     * @param courseDropdownNumber - The course dropdown number to click
     * @param courseDropdownName - The course name to click the dropdown
     * @param option - The option to click: "import", "homepage", "rubric", "markbook", "analytics", "calendar", "planner", "report", "modify", "delete",
     */
    async clickCourseDotMenuOption(
        courseDropdownNumber: number,
        courseDropdownName: string,
        option: string,
    ): Promise<void> {
        await this.clickCourseDotMenu(courseDropdownNumber);
        await this.page.getByTestId(`course-list-${option}-${courseDropdownName}`).click();
    }

    /**
     * Clicks a course 3-dot menu
     * @param courseDropdownNumber - The course dropdown number to click
     */
    async clickCourseDotMenu(courseDropdownNumber: number): Promise<void> {
        await this.page.getByTestId(`${CourseList.DATA_TEST_COURSE_DOT_MENU}-${courseDropdownNumber}`).click();
    }

    /**
     * Clicks a course down arrow
     * @param courseName - The course name to click
     */
    async clickCourseDownArrow(courseName: string): Promise<void> {
        await this.page.getByTestId(`course-list-arrow-${courseName}`).click();
    }

    /**
     * Clicks import into class
     * @param courseDropdownNumber - The course dropdown number to click
     */
    async clickImportIntoClass(courseDropdownNumber: number): Promise<void> {
        await this.page.click(`a[href="/course/import/${courseDropdownNumber}/folder/0/0?returnUrl=L2NvdXJzZS9saXN0"]`);
    }

    /**
     * expands the course, count how many units are presented and check if the proper number of units is presented
     * @param courseName - The course name to expand
     * @param courseId - The course ID to expand
     * @param expectedUnitCount - The expected number of units to be presented
     */
    async verifyCourseUnitsCount(courseName: string, courseId: number, expectedUnitCount: number): Promise<void> {
        await this.clickCourseDownArrow(courseName);
        const unitList = this.page.locator(`div[data-collapsable="course-${courseId}"]`);
        const units = unitList.locator("ul.unit-list > li").count();
        await expect(units).resolves.toBe(expectedUnitCount);
    }

    /**
     * check whether course name is within the course list or not
     * by passing false it check the course name is not within the course list
     * @param newCourseName - The course name to verify
     * @param isPresent - Whether the course name should be present or not
     */
    async verifyCourseNameInList(newCourseName: string, isPresent: boolean = true): Promise<void> {
        const courseLocator = this.page.getByTestId(`course-list-${newCourseName}`);
        if (isPresent) {
            await expect(courseLocator).toBeVisible();
        } else {
            await expect(courseLocator).not.toBeVisible();
        }
    }
}

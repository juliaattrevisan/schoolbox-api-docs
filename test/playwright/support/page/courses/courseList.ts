import { Page } from "@playwright/test";

export class CourseList {
    private page: Page;

    static readonly URL = "/course/list";

    static readonly DATATEST_COURSE_DOT_MENU = "sbx-pop-out-courseDropdown"; // Append "-[courseDropdownNumber]"

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to course list page
     */
    async goto() {
        await this.page.goto(CourseList.URL);
    }

    /**
     * Clicks a course 3-dot menu
     * @param { number } courseDropdownNumber - The course dropdown number to click
     */
    async clickCourseDotMenu(courseDropdownNumber: number) {
        await this.page.getByTestId(`${CourseList.DATATEST_COURSE_DOT_MENU}-${courseDropdownNumber}`).click();
    }

    /**
     * Clicks a course down arrow
     * @param { number } courseId - The course ID to click
     */
    async clickCourseDownArrow(courseId: number) {
        await this.page.click(`a.icon-down-arrow[data-collapser=course-${courseId}]`);
    }

    /**
     * Clicks import into class
     * @param { number } courseDropdownNumber - The course dropdown number to click
     */
    async clickImportIntoClass(courseDropdownNumber: number) {
        await this.page.click(`a[href="/course/import/${courseDropdownNumber}/folder/0/0?returnUrl=L2NvdXJzZS9saXN0"]`);
    }
}

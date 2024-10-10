import type { Page } from "@playwright/test";

export default class CourseList {
    private page: Page;

    static readonly URL = "/course/list";

    static readonly DATATEST_COURSE_DOT_MENU = "sbx-pop-out-courseDropdown"; // Append "-[courseDropdownNumber]"

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to course list page
     */
    async goto(): Promise<void> {
        await this.page.goto(CourseList.URL);
    }

    /**
     * Clicks a course 3-dot menu
     * @param courseDropdownNumber - The course dropdown number to click
     */
    async clickCourseDotMenu(courseDropdownNumber: number): Promise<void> {
        await this.page.getByTestId(`${CourseList.DATATEST_COURSE_DOT_MENU}-${courseDropdownNumber}`).click();
    }

    /**
     * Clicks a course down arrow
     * @param courseId - The course ID to click
     */
    async clickCourseDownArrow(courseId: number): Promise<void> {
        await this.page.click(`a.icon-down-arrow[data-collapser=course-${courseId}]`);
    }

    /**
     * Clicks import into class
     * @param courseDropdownNumber - The course dropdown number to click
     */
    async clickImportIntoClass(courseDropdownNumber: number): Promise<void> {
        await this.page.click(`a[href="/course/import/${courseDropdownNumber}/folder/0/0?returnUrl=L2NvdXJzZS9saXN0"]`);
    }
}

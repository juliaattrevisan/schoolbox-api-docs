import { Page } from "@playwright/test";

export default class CourseModify {
    private page: Page;

    static readonly URL = "/course/modify/{course_id}";

    static readonly URL_ID_FRAGMENT = "{course_id}";

    static readonly SELECTOR_LOCATION = "[data-course-parent-selector]";

    static readonly SELECTOR_USE_FOLDER = "[data-selection] [data-select]";

    static readonly SELECTOR_SAVE_COURSE = "#update-course-form [type=submit][value='Save Course']";

    static readonly NEW_LESSON_PLAN_BUTTON = "New Lesson Plan";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Go to a course modify page for a given course ID
     * @param courseId - The course ID to go to
     */
    async goto(courseId: number): Promise<void> {
        await this.page.goto(CourseModify.URL.replace(CourseModify.URL_ID_FRAGMENT, courseId.toString()));
    }

    /**
     * Clicks a unit arrow
     * @param unitNumber - The unit number to click
     */
    async clickUnitArrow(unitNumber: number): Promise<void> {
        await this.page.click(`a.icon-down-arrow[data-collapser=unit-${unitNumber}]`);
    }

    /**
     * Clicks an export icon
     * @param assessmentNumber - The assessment number to click
     */
    async clickExportIcon(assessmentNumber: number): Promise<void> {
        await this.page.click(
            `a[href="/learning/export/assessment/${assessmentNumber}?returnUrl=L2NvdXJzZS9tb2RpZnkvNiMxNQ%3D%3D"]`,
        );
    }

    /**
     * Clicks the new lesson plan button
     */
    async clickNewLessonPlanButton(): Promise<void> {
        await this.page.click(CourseModify.NEW_LESSON_PLAN_BUTTON);
    }
}

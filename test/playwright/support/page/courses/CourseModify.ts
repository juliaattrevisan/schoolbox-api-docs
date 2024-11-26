import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class CourseModify {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly URL = "/course/modify/{course_id}";

    static readonly URL_ID_FRAGMENT = "{course_id}";

    static readonly SELECTOR_LOCATION = "[data-course-parent-selector]";

    static readonly SELECTOR_USE_FOLDER = `[data-selection] [data-select]`;

    static readonly SELECTOR_SAVE_COURSE = "#update-course-form [type=submit][value='Save Course']";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
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
     * Clicks the new lesson plan button by passing the button name: lesson-plan,quz,due-work,task,project
     * @param buttonName - The name of the button to click
     */
    async clickNewLessonPlanButton(buttonName: string): Promise<void> {
        await this.page.click(`a.button.icon-${buttonName}`);
    }

    /**
     * Clicks select location
     */
    async clickSelectLocation(): Promise<void> {
        await this.page.locator(CourseModify.SELECTOR_LOCATION).click();
        // const useThisFolderButton = this.page.locator(CourseModify.SELECTOR_USE_FOLDER);
        // await expect(useThisFolderButton).toBeEnabled();
    }
}

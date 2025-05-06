import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class CourseModify {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly URL = "/course/modify/{course_id}";

    static readonly URL_ID_FRAGMENT = "{course_id}";

    static readonly SELECTOR_LOCATION = "[data-course-parent-selector]";

    static readonly SELECTOR_USE_FOLDER = `[data-selection] [data-select]`;

    static readonly SELECTOR_SAVE_COURSE = "#update-course-form [type=submit][value='Save Course']";

    static readonly DATATEST_COURSE_NEW_UNIT_NAME = "course-new-unit-name";

    static readonly DATATEST_COURSE_NEW_UNIT_ADD = "course-new-unit-add";

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
     * Clicks a unit 3-dot menu
     * @param unitNumber - The unit number to click
     */
    async clickUnitMenu(unitNumber: number): Promise<void> {
        await this.page.getByTestId(`sbx-pop-out-unit-${unitNumber}-modify-button`).click();
    }

    /**
     * Clicks the import unit to classes button under the open 3-dot menu
     */
    async clickImportUnitToClasses(): Promise<void> {
        await this.page.locator(`.f-dropdown a[data-action="import-unit"]:visible`).click();
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
        await this.page.click(`a[href^="/learning/export/assessment/${assessmentNumber}"]`);
    }

    /**
     * Clicks the new activity button by passing the button name: lesson-plan,quz,due-work,task,project
     * @param buttonName - The name of the button to click
     */
    async clickNewActivityButton(buttonName: string): Promise<void> {
        // there may be a few, so find the visible one
        await this.page.locator(`a.button.icon-${buttonName}:visible`).click();
    }

    /**
     * Clicks select location
     */
    async clickSelectLocation(): Promise<void> {
        await this.page.locator(CourseModify.SELECTOR_LOCATION).click();
    }

    /**
     * Creates a new unit with a specified name
     * @param unitName - The name of the unit to create
     * @returns The unitSpecId of the newly created unit
     */
    async createNewUnit(unitName: string): Promise<number> {
        await this.page.getByTestId(CourseModify.DATATEST_COURSE_NEW_UNIT_NAME).fill(unitName);

        const addButton = this.page.getByTestId(CourseModify.DATATEST_COURSE_NEW_UNIT_ADD);
        await addButton.click();

        // Wait for a moment for the new unit to appear as .last() won't wait for it
        await expect(addButton).toBeEnabled();
        await this.page.waitForTimeout(500);

        const unitSpecId = await this.page.locator("li[data-unit-spec]").last().getAttribute("data-unit-spec");
        return Number(unitSpecId);
    }
}

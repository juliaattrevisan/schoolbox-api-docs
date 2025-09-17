import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import Homepage from "./Homepage";

export default class CourseOutline {
    private tester: AcceptanceTester;

    private page: Page;

    private homepage: Homepage;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;

        this.homepage = new Homepage(tester, page);
    }

    /**
     * Uses the course outline component to find an unit and clicks its 3-dot menu
     * clicks one of the options in the menu: project, task, due work, quiz, lesson plan
     * @param unitName - The name of the unit to find in the course outline
     * @param menuOption - The name of the menu option to click: project, task, due-work, quiz, lesson-plan
     */
    async selectOptionUnitDotMenu(unitName: string, menuOption: string): Promise<void> {
        // Find the course outline and the unit we created inside it
        this.tester.isGoingTo("find the unit in the course outline");
        const courseOutline = this.homepage.locateComponentByName("Course Outline");
        const courseOutlineId = await courseOutline.getAttribute("id");
        const courseOutlineUnit = courseOutline.locator("li[data-unit]").filter({ hasText: unitName });
        const courseOutlineUnitId = await courseOutlineUnit.getAttribute("data-unit");

        this.tester.isGoingTo("click to open the 3-dot menu of the unit");
        await courseOutlineUnit
            .getByTestId(`sbx-pop-out-${courseOutlineId}-unit-options${courseOutlineUnitId}-button`)
            .click();

        this.tester.isGoingTo("click one of the dropdown menu options");
        await courseOutlineUnit.getByTestId(`course-outline-menu-add-${menuOption}`).click();
    }

    /**
     * Uses the course outline component to find an unit and click the activity 3-dot menu
     * @param unitName - The name of the unit to find in the course outline
     * @param activityId - The id of the activity to find in the unit
     * @param actionActivityId - The action and activity id to click in the menu - eg save-course-664, delete-664
     */
    async selectOptionActivityDotMenu(
        unitName: string,
        activityId: number | string,
        actionActivityId: string,
    ): Promise<void> {
        // Find the course outline and the unit we created inside it
        this.tester.isGoingTo("find the unit in the course outline");
        const courseOutline = this.homepage.locateComponentByName("Course Outline");
        const courseOutlineId = await courseOutline.getAttribute("id");
        const courseOutlineUnit = courseOutline.locator("li[data-unit]").filter({ hasText: unitName });

        this.tester.isGoingTo("click to open the 3-dot menu of the activity");
        await courseOutlineUnit.getByTestId(`sbx-pop-out-${courseOutlineId}-options-task${activityId}-button`).click();

        this.tester.isGoingTo("click one of the dropdown menu options");
        await courseOutlineUnit.getByTestId(`activity-${actionActivityId}`).click();
    }

    /**
     * Uses the course outline component to view the status of an activity
     * @param unitId - The ID of the unit to find
     * @param activityName - The name of the activity to find
     */
    async viewUnitActivityStatus(unitId: string | number, activityName: string): Promise<void> {
        // Find the course outline and the unit we created inside it
        this.tester.isGoingTo("find the unit in the course outline");
        const courseOutline = this.homepage.locateComponentByName("Course Outline");
        const courseOutlineUnit = courseOutline.locator(`li[data-unit="${unitId}"]`);

        this.tester.isGoingTo("click to view the status of the activity");
        const activities = courseOutlineUnit.locator("li[data-unit-assessment]");
        const activity = activities.filter({ hasText: activityName });
        const activityId = await activity.getAttribute("data-unit-assessment");
        const courseOutlineId = await courseOutline.getAttribute("id");
        await activity.getByTestId(`sbx-pop-out-${courseOutlineId}-options-task${activityId}-button`).click();

        await activity.locator("[data-dropdown-content] a.icon-progress").click();
    }

    /**
     * Uses the course outline component to find an activity and go to the summary page for it
     * @param unitId - The ID of the unit to find
     * @param activityName - The name of the activity to find
     */
    async viewUnitActivitySummary(unitId: string | number, activityName: string): Promise<void> {
        // Find the course outline and the unit we created inside it
        this.tester.isGoingTo("find the unit in the course outline");
        const courseOutline = this.homepage.locateComponentByName("Course Outline");
        const courseOutlineUnit = courseOutline.locator(`li[data-unit="${unitId}"]`);

        this.tester.isGoingTo("click to view the summary of the activity");
        const activities = courseOutlineUnit.locator("li[data-unit-assessment]");
        const activity = activities.filter({ hasText: activityName });
        await activity.locator("a").first().click();
    }

    /**
     * licks the save to course button in the activity 3-dot menu
     * confirms it in the confirm dialog
     * @param unitName - The name of the unit to find in the course outline
     * @param activityId - The id of the activity to find in the unit
     * @param actionActivityId - The action and activity id to click in the menu - eg save-course-664
     */
    async saveActivityToCourse(unitName: string, activityId: number | string, actionActivityId: string): Promise<void> {
        this.tester.isGoingTo("click the save to course button in the modal");
        await this.selectOptionActivityDotMenu(unitName, activityId, actionActivityId);

        this.tester.isGoingTo("confirm in the confirm dialog");

        await this.page.getByTestId(`confirm-dialog-yes-button${activityId}`).getByText("Yes").click();
    }

    /**
     * clicks the delete buttons in the activity 3-dot menu
     * confirms it in the confirm dialog
     * @param unitName - The name of the unit to find in the course outline
     * @param activityId - The id of the activity to find in the unit
     * @param actionActivityId - The action and activity id to click in the menu - eg delete-664
     */
    async deleteActivity(unitName: string, activityId: number | string, actionActivityId: string): Promise<void> {
        this.tester.isGoingTo("click the save to course button in the modal");
        await this.selectOptionActivityDotMenu(unitName, activityId, actionActivityId);

        this.tester.isGoingTo("confirm in the confirm dialog");
        await this.page.getByTestId("confirm-dialog-confirm-button").getByText("Yes, permanently delete").click();
        await this.page.getByTestId("confirm-dialog-confirm-button").isHidden();
    }
}

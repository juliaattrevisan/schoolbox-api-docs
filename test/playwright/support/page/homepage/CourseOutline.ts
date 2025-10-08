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
}

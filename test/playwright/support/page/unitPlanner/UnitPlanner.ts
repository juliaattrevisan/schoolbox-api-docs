import { expect } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page, Locator } from "@playwright/test";

export default class UnitPlanner {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/course/plan/{course_id}";

    static readonly DATA_TEST_CREATE_ACTIVITY_BUTTON =
        "sbx-pop-out-dropdown-activities-list-activity-{unitId}-activities-1-button";

    static readonly DATA_TEST_CREATE_ACTIVITY_OPTION =
        "unit-planner-create-{activityType}-dropdown-activities-list-activity-{unitId}-activities-1";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Generates route for this page.
     * @param courseId - The ID of the page
     */
    async goto(courseId: number): Promise<void> {
        await this.page.goto(UnitPlanner.URL.replace("{course_id}", courseId.toString()));
    }

    /**
     * Click the create activity button
     * @param unitId - The ID of the unit
     */
    async clickCreateActivityButton(unitId: number): Promise<void> {
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_CREATE_ACTIVITY_BUTTON.replace("{unitId}", unitId.toString()))
            .click();
    }

    /**
     * Click an option under the create activity dropdown: project, due-work, quiz, task or lesson-plan
     * @param activityType - The type of activity to create
     * @param unitId - The ID of the unit
     */
    async clickCreateActivityOption(activityType: string, unitId: number): Promise<void> {
        await this.page
            .getByTestId(
                UnitPlanner.DATA_TEST_CREATE_ACTIVITY_OPTION.replace("{activityType}", activityType).replace(
                    "{unitId}",
                    unitId.toString(),
                ),
            )
            .click();
    }
}

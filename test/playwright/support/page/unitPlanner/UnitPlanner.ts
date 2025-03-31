import { expect } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";

export default class UnitPlanner {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/course/plan/{course_id}";

    static readonly DATA_TEST_CREATE_ACTIVITY_BUTTON =
        "sbx-pop-out-dropdown-activities-list-activity-{unitId}-activities-1-button";

    static readonly DATA_TEST_CREATE_ACTIVITY_OPTION =
        "unit-planner-create-{activityType}-dropdown-activities-list-activity-{unitId}-activities-1";

    static readonly DATA_TEST_ACTIVITY_DOT_MENU = "sbx-pop-out-dropdown-unitplanner-activity-{activityId}-button";

    static readonly DATA_TEST_ACTIVITY_EXPORT = "unit-planner-export-activity-{activityId}";

    static readonly DATA_TEST_ACTIVITY_MODIFY = "unit-planner-modify-activity-{activityId}";

    static readonly DATA_TEST_ACTIVITY_DELETE = "unit-planner-delete-activity-{activityId}";

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

    /**
     * Click the dot menu of an activity
     * @param activityId - The ID of the activity
     */
    async clickActivityDotMenu(activityId: number): Promise<void> {
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_ACTIVITY_DOT_MENU.replace("{activityId}", activityId.toString()))
            .click();
    }

    /**
     * Click the modify button of an activity
     * @param activityId - The ID of the activity
     */
    async clickActivityModify(activityId: number): Promise<void> {
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_ACTIVITY_MODIFY.replace("{activityId}", activityId.toString()))
            .click();
    }

    /**
     * Click the delete button of an activity
     * @param activityId - The ID of the activity
     */
    async clickActivityDelete(activityId: number): Promise<void> {
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_ACTIVITY_DELETE.replace("{activityId}", activityId.toString()))
            .click();
        await this.page.locator("button.button.icon-approve.submit").last().click();
        await expect(this.page.getByTestId("toast")).toHaveAttribute("data-test-is-success", "true");
    }

    /**
     * Checks if the optioins under Create Activity menu are presented
     * @param activityType - The type of activity to create:: project, due-work, quiz, task or lesson-plan
     * @param unitId - The ID of the unit
     */
    async checkCreateActivityOptions(activityType: string, unitId: number): Promise<void> {
        await this.page
            .getByTestId(
                UnitPlanner.DATA_TEST_CREATE_ACTIVITY_OPTION.replace("{activityType}", activityType).replace(
                    "{unitId}",
                    unitId.toString(),
                ),
            )
            .isVisible();
    }

    /**
     * Checks if the optioins under the activity menu are presented:: export to classes, modify and delete
     * @param activityId - The ID of the activity
     */
    async checkActivityMenuOptions(activityId: number): Promise<void> {
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_ACTIVITY_EXPORT.replace("{activityId}", activityId.toString()))
            .isVisible();
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_ACTIVITY_MODIFY.replace("{activityId}", activityId.toString()))
            .isVisible();
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_ACTIVITY_DELETE.replace("{activityId}", activityId.toString()))
            .isVisible();
    }
}

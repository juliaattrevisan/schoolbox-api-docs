import { expect } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";

export default class UnitPlanner {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/course/plan/{course_id}";

    static readonly DATA_TEST_UNIT_LINK = "unit-planner-unit-link-{unitId}";

    static readonly DATA_TEST_CREATE_TEXT_BOX_BUTTON = "unit-planner-textbox-action-create-{unitId}-overview-1";

    static readonly DATA_TEST_TEXT_BOX_SAVE_BUTTON = "unit-planner-textbox-action-save-{unitId}-overview-1";

    static readonly DATA_TEST_UNIT_ROW = "unit-row-{unitId}";

    static readonly DATA_TEST_CREATE_ACTIVITY_BUTTON =
        "sbx-pop-out-dropdown-activities-list-activity-{unitId}-activities-1-button";

    static readonly DATA_TEST_CREATE_ACTIVITY_OPTION =
        "unit-planner-create-{activityType}-dropdown-activities-list-activity-{unitId}-activities-1";

    static readonly DATA_TEST_ACTIVITY_DOT_MENU = "sbx-pop-out-dropdown-unitplanner-activity-{activityId}-button";

    static readonly DATA_TEST_ACTIVITY_EXPORT = "unit-planner-export-activity-{activityId}";

    static readonly DATA_TEST_ACTIVITY_MODIFY = "unit-planner-modify-activity-{activityId}";

    static readonly DATA_TEST_ACTIVITY_DELETE = "unit-planner-delete-activity-{activityId}";

    static readonly DATA_TEST_SELECT_CURRICULUM_LINK = "unit-planner-select-curriculum-code-{unitId}";

    static readonly DATA_TEST_REMOVE_CURRICULUM_ICON = "fn-list-node-remove-{nodeId}";

    static readonly DATA_TEST_MODAL_CONFIRM_DELETE_NODE = "fn-delete-node-confirm-{folderId}";

    static readonly DATA_TEST_UNIT_PLANNER_TABS = "unit-planner-tabs-{tabName}";

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
     * Click the link to a unit
     * @param unitId - The ID of the unit
     */
    async clickUnitLink(unitId: number): Promise<void> {
        await this.page.getByTestId(UnitPlanner.DATA_TEST_UNIT_LINK.replace("{unitId}", unitId.toString())).click();
    }

    /**
     * Click the create text box button
     * @param unitId - The ID of the unit
     */
    async clickCreateTextBoxButton(unitId: number): Promise<void> {
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_CREATE_TEXT_BOX_BUTTON.replace("{unitId}", unitId.toString()))
            .click();
        await this.page.waitForTimeout(500); // Wait for the textbox to be focused
    }

    /**
     * Click the save button in the text box
     * @param unitId - The ID of the unit
     */
    async clickTextBoxSaveButton(unitId: number): Promise<void> {
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_TEXT_BOX_SAVE_BUTTON.replace("{unitId}", unitId.toString()))
            .click();
    }

    /**
     * Get the current order of units
     * @returns - The order of unit IDs
     */
    async getUnitOrder(): Promise<string[]> {
        const allUnitRowSelector = /^unit-row-/;
        const pageOrder = await this.page
            .getByTestId(allUnitRowSelector)
            .evaluateAll((unitRows) => unitRows.map((unitRow) => unitRow.getAttribute("data-test-unit-id")));
        return pageOrder.filter((id) => id !== null);
    }

    /**
     * drag and drop a unit to a new position
     * @param unitIdToDrag - The ID of the unit to drag to
     * @param unitIdToDragFrom - The ID of the unit to drag from
     */
    async dragAndDropUnit(unitIdToDrag: number, unitIdToDragFrom: number): Promise<void> {
        const unitRow = this.page.getByTestId(
            UnitPlanner.DATA_TEST_UNIT_ROW.replace("{unitId}", unitIdToDrag.toString()),
        );
        const unitDragHandle = unitRow.getByTestId("unit-row-drag-handle");
        await unitDragHandle.scrollIntoViewIfNeeded();
        await unitDragHandle.hover();
        await this.page.mouse.down();

        const unitPlannerContainer = this.page.getByTestId("unit-planner-container");

        // Move mouse to top of container, then wait for the reorder to happen
        await unitPlannerContainer.hover({ position: { x: 0, y: 0 } });

        this.tester.isGoingTo("keep dragging and wait for the units to reorder");
        let newUnitOrder = await this.getUnitOrder();
        const startTime = Date.now();
        while (
            newUnitOrder.indexOf(unitIdToDrag.toString()) > newUnitOrder.indexOf(unitIdToDragFrom.toString()) &&
            Date.now() - startTime < 5000 // Timeout after 5 seconds
        ) {
            newUnitOrder = await this.getUnitOrder();
        }

        await this.page.mouse.up();
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
     * Checks if the options under Create Activity menu are presented
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
     * Checks if the options under the activity menu are presented:: export to classes, modify and delete
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

    /**
     * Click the select curriculum link
     * @param unitId - The ID of the unit
     */
    async clickSelectCurriculumLink(unitId: number): Promise<void> {
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_SELECT_CURRICULUM_LINK.replace("{unitId}", unitId.toString()))
            .click();
    }

    /**
     * Click the remove curriculum icon
     * @param nodeId - The ID of the node
     */
    async clickRemoveNodeIcon(nodeId: number): Promise<void> {
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_REMOVE_CURRICULUM_ICON.replace("{nodeId}", nodeId.toString()))
            .click();
    }

    /**
     * Click the confirm delete node button
     */

    async clickConfirmDeleteNodeButton(folderId: number): Promise<void> {
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_MODAL_CONFIRM_DELETE_NODE.replace("{folderId}", folderId.toString()))
            .click();
        await expect(this.page.getByTestId("toast")).toHaveAttribute("data-test-is-success", "true");
    }

    /**
     * Click the confirm delete node button
     * @param folderId - The ID of the folder
     */

    async clickUnitPlannerTabs(tabName: string): Promise<void> {
        await this.page.getByTestId(UnitPlanner.DATA_TEST_UNIT_PLANNER_TABS.replace("{tabName}", tabName)).click();
    }
}

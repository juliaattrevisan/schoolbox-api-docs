import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";
import type { ActivityType } from "@schoolboxAssessment/Types/assessmentTypes";

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

    static readonly DATA_TEST_ACTIVITY_DROPDOWN_OPTIONS = "unit-planner-{action}-activity-{activityId}";

    static readonly DATA_TEST_SELECT_CURRICULUM_LINK = "unit-planner-select-curriculum-code-{unitId}";

    static readonly DATA_TEST_REMOVE_CURRICULUM_ICON = "fn-list-node-remove-{nodeId}";

    static readonly DATA_TEST_MODAL_CONFIRM_DELETE_NODE = "fn-delete-node-confirm-{folderId}";

    static readonly DATA_TEST_UNIT_PLANNER_TABS = "unit-planner-tabs-{tabName}";

    static readonly DATA_TEST_RUBRIC_MANAGE_DROPDOWN = "sbx-pop-out-dropdown-unit-{unitId}-rubric-capabilities-button";

    static readonly DATA_TEST_CURRICULUM_CREATE_MODIFY = "unit-planner-curriculum-{action}-{folderId}";

    static readonly DATA_TEST_ADD_UNITS_NAME_INPUT = "up-add-units-name-input-{index}";

    static readonly DATA_TEST_ADD_UNITS_ADD_BUTTON = "up-add-units-add";

    static readonly DATA_TEST_ADD_UNITS_CREATE_BUTTON = "up-add-units-create";

    static readonly DATA_TEST_CREATE_UNITS_BUTTON = "unit-planner-create-units";

    static readonly DATA_TEST_DROPDOWN_MENU = "sbx-pop-out-unit-dropdown-button";

    static readonly DATA_TEST_IMPORT_INTO_CLASS_OPTION = "up-header-{courseId}-import-link";

    static readonly DATA_TEST_VIEW_MORE = "unit-planner-view-more-button-{unitId}-{columType}";

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
        return pageOrder.filter((id): id is string => id !== null);
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
     * Get the current order of activities
     * @returns - The order of activity IDs
     */
    async getActivityOrder(): Promise<string[]> {
        const allActivityRowSelector = /^unit-planner-activity-title-/;
        const pageOrder = await this.page
            .getByTestId(allActivityRowSelector)
            .evaluateAll((activityRows) =>
                activityRows.map((activityRow) => activityRow.getAttribute("data-test-activity-id")),
            );
        return pageOrder.filter((id): id is string => id !== null);
    }

    /**
     * Check whether create activity button is presented in the unit or not
     * pass true to check it is visible and false to check it is not visible
     * @param unitId - The ID of the unit
     * @param shouldBeVisible - Whether the button should be visible or not
     */
    async checkCreateActivityButtonVisibility(unitId: number, shouldBeVisible: boolean): Promise<void> {
        const createActivityButtonLocator = this.page.getByTestId(
            UnitPlanner.DATA_TEST_CREATE_ACTIVITY_BUTTON.replace("{unitId}", unitId.toString()),
        );
        if (shouldBeVisible) {
            await expect(createActivityButtonLocator).toBeVisible();
        } else {
            await expect(createActivityButtonLocator).not.toBeVisible();
        }
    }

    /**
     * Check whether the create units button is presented in the unit planner header or not
     * @param shouldBeVisible - Whether the button should be visible or not
     */
    async checkCreateUnitsButtonVisibility(shouldBeVisible: boolean): Promise<void> {
        const createUnitsButtonLocator = this.page.getByTestId(UnitPlanner.DATA_TEST_CREATE_UNITS_BUTTON);
        if (shouldBeVisible) {
            await expect(createUnitsButtonLocator).toBeVisible();
        } else {
            await expect(createUnitsButtonLocator).not.toBeVisible();
        }
    }

    /**
     * Navigate to a unit planner page and check whether the create units button is presented in the unit or not
     * pass true to check it is visible and false to check it is not visible
     * @param courseId - The ID of the course
     * @param shouldBeVisible - Whether the button should be visible or not
     */
    async navigateAndCheckCreateUnitsButton(courseId: number, shouldBeVisible: boolean): Promise<void> {
        await this.goto(courseId);
        await this.checkCreateUnitsButtonVisibility(shouldBeVisible);
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
     * Check if the options under Create Activity menu are presented based on the activity id and action
     * @param action - The action to check: export, modify or delete
     * @param activityId - The ID of the activity
     * @param shouldBeVisible - Whether the option should be visible or not; pass true to check it is visible and false to check it is not visible
     */
    async checkActivityMenuOptionVisibility(
        action: string,
        activityId: number,
        shouldBeVisible: boolean,
    ): Promise<void> {
        const activityMenuOptionLocator = this.page.getByTestId(
            UnitPlanner.DATA_TEST_ACTIVITY_DROPDOWN_OPTIONS.replace("{action}", action).replace(
                "{activityId}",
                activityId.toString(),
            ),
        );
        if (shouldBeVisible) {
            await expect(activityMenuOptionLocator).toBeVisible();
        } else {
            await expect(activityMenuOptionLocator).not.toBeVisible();
        }
    }

    /**
     * Click an option under the create activity dropdown: project, due-work, quiz, task or lesson-plan
     * @param activityType - The type of activity to create
     * @param unitId - The ID of the unit
     */
    async clickCreateActivityOption(activityType: ActivityType, unitId: number): Promise<void> {
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
        await this.page.locator('button.button.submit:has(sbx-icon[name="approve"])').last().click();
        await expect(this.page.getByTestId("toast")).toHaveAttribute("data-test-is-success", "true");
    }

    /**
     * Checks if the options under Create Activity menu are presented
     * @param activityType - The type of activity to create:: project, due-work, quiz, task or lesson-plan
     * @param unitId - The ID of the unit
     */
    async checkCreateActivityOptions(activityType: ActivityType, unitId: number): Promise<void> {
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
     * @param folderId - The ID of the folder
     */
    async clickConfirmDeleteNodeButton(folderId: number): Promise<void> {
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_MODAL_CONFIRM_DELETE_NODE.replace("{folderId}", folderId.toString()))
            .click();
        await expect(this.page.getByTestId("toast")).toHaveAttribute("data-test-is-success", "true");
    }

    /**
     * Click the confirm delete node button
     * @param tabName - The name of the tab
     */
    async clickUnitPlannerTabs(tabName: string): Promise<void> {
        await this.page.getByTestId(UnitPlanner.DATA_TEST_UNIT_PLANNER_TABS.replace("{tabName}", tabName)).click();
    }

    /**
     * Check if the unit is presented in the unit planner
     * pass true to check it is visible and false to check it is not visible
     * @param unitId - The ID of the unit
     * @param shouldBeVisible - Whether the unit should be visible or not
     */
    async checkUnitVisibility(unitId: number, shouldBeVisible: boolean): Promise<void> {
        const unitLinkLocator = this.page.getByTestId(
            UnitPlanner.DATA_TEST_UNIT_LINK.replace("{unitId}", unitId.toString()),
        );
        if (shouldBeVisible) {
            await expect(unitLinkLocator).toBeVisible();
        } else {
            await expect(unitLinkLocator).not.toBeVisible();
        }
    }

    /**
     * Check if the create text box button is presented in the unit
     * pass true to check it is visible and false to check it is not visible
     * @param unitId - The ID of the unit
     * @param shouldBeVisible - Whether the button should be visible or not
     */
    async checkCreateTextBoxButtonVisibility(unitId: number, shouldBeVisible: boolean): Promise<void> {
        const createTextBoxButtonLocator = this.page.getByTestId(
            UnitPlanner.DATA_TEST_CREATE_TEXT_BOX_BUTTON.replace("{unitId}", unitId.toString()),
        );
        if (shouldBeVisible) {
            await expect(createTextBoxButtonLocator).toBeVisible();
        } else {
            await expect(createTextBoxButtonLocator).not.toBeVisible();
        }
    }

    /**
     * Check whether the create or modify curriculum button is presented in the unit or not
     * pass 'create' to check the create button and 'modify' to check the modify button
     * pass true to check it is visible and false to check it is not visible
     * @param action - 'create' or 'modify'
     * @param folderId - The ID of the folder
     * @param shouldBeVisible - Whether the button should be visible or not
     */
    async checkCurriculumCreateModifyButtonVisibility(
        action: "create" | "modify",
        folderId: number,
        shouldBeVisible: boolean,
    ): Promise<void> {
        const curriculumButtonLocator = this.page.getByTestId(
            UnitPlanner.DATA_TEST_CURRICULUM_CREATE_MODIFY.replace("{action}", action).replace(
                "{folderId}",
                folderId.toString(),
            ),
        );
        if (shouldBeVisible) {
            await expect(curriculumButtonLocator).toBeVisible();
        } else {
            await expect(curriculumButtonLocator).not.toBeVisible();
        }
    }

    /**
     * Click the manage rubric capabilities dropdown
     * @param unitId - The ID of the unit
     */
    async clickManageRubricCapabilitiesDropdown(unitId: number): Promise<void> {
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_RUBRIC_MANAGE_DROPDOWN.replace("{unitId}", unitId.toString()))
            .click();
    }

    /**
     * Checks whether the Manage Course Capabilities and/or Manage Unit Capabilities options are visible in the dropdown or not
     * set true to check course option and true to check unit option
     * set false to check it is not visible
     * @param unitId - The ID of the unit
     * @param checkCourseOption - Whether to check for Manage Course Capabilities option
     * @param checkUnitOption - Whether to check for Manage Unit Capabilities option
     */
    async checkRubricCapabilitiesOptions(
        unitId: number,
        checkCourseOption: boolean,
        checkUnitOption: boolean,
    ): Promise<void> {
        const dropdownLocator = this.page.locator(`ul[id="dropdown-unit-${unitId}-rubric-capabilities"]`);
        if (checkCourseOption === true) {
            await expect(dropdownLocator).toContainText("Manage Course Capabilities");
        } else {
            await expect(dropdownLocator).not.toContainText("Manage Course Capabilities");
        }
        if (checkUnitOption === true) {
            await expect(dropdownLocator).toContainText("Manage Unit Capabilities");
        } else {
            await expect(dropdownLocator).not.toContainText("Manage Unit Capabilities");
        }
    }

    /**
     * Create multiple units within the add units modal
     * @param unitNames - Array of unit names to create
     */
    async createUnitsInModal(unitNames: string[]): Promise<void> {
        if (unitNames.length === 0) {
            throw new Error("At least one unit name must be provided");
        }

        // Confirm modal is visible
        await this.page.getByTestId("up-add-units-title").isVisible();

        // Fill in the first unit name (input field already exists)
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_ADD_UNITS_NAME_INPUT.replace("{index}", "0"))
            .fill(unitNames[0]);

        // Add additional units by clicking "Add another" and filling in the names
        for (let i = 1; i < unitNames.length; i += 1) {
            await this.page.getByTestId(UnitPlanner.DATA_TEST_ADD_UNITS_ADD_BUTTON).click();
            await this.page
                .getByTestId(UnitPlanner.DATA_TEST_ADD_UNITS_NAME_INPUT.replace("{index}", i.toString()))
                .fill(unitNames[i]);
        }

        // Click Create to create all units
        await this.page.getByTestId(UnitPlanner.DATA_TEST_ADD_UNITS_CREATE_BUTTON).click();

        // Verify each unit is visible on the page after creation
        for (const unitName of unitNames) {
            await expect(this.page.getByTestId(/^unit-planner-unit-link-/).getByText(unitName)).toBeVisible();
        }

        // refresh the page and ensure the units are still there
        await this.page.reload();
        for (const unitName of unitNames) {
            await expect(this.page.getByTestId(/^unit-planner-unit-link-/).getByText(unitName)).toBeVisible();
        }
    }

    /**
     * Delete a unit from the database
     * @param db - The database helper
     * @param unitName - The name of the unit to delete
     */
    static async dbDeleteUnit(db: DatabaseHelper, unitName: string): Promise<void> {
        const folderId = (await db.grabFromDatabase("folder", "id", {
            name: unitName,
        })) as number | null;
        if (folderId !== null) {
            await db.dontHaveInDatabase("homepage_config", { cid: folderId });
            await db.dontHaveInDatabase("perms", { cid: folderId });
            await db.dontHaveInDatabase("course_unit_specification", { folder_id: folderId });
            await db.dontHaveInDatabase("folder", { id: folderId });
        }
    }

    /**
     * Delete a course from the database
     * @param db - The database helper
     * @param courseName - The name of the course to delete
     */
    static async dbDeleteCourse(db: DatabaseHelper, courseName: string): Promise<void> {
        const folderId = (await db.grabFromDatabase("folder", "id", {
            name: courseName,
        })) as number | null;
        if (folderId !== null) {
            await db.dontHaveInDatabase("course_specification", { folder_id: folderId });
            await db.dontHaveInDatabase("homepage_config", { cid: folderId });
            await db.dontHaveInDatabase("perms", { cid: folderId });
            await db.dontHaveInDatabase("folder", { id: folderId });
        }
    }

    /**
     * Click the unit planner 3-dot menu
     */
    async clickUnitPlannerDotMenu(): Promise<void> {
        await this.page.getByTestId(UnitPlanner.DATA_TEST_DROPDOWN_MENU).click();
    }

    /**
     * Click the Import into classes option from the unit planner dropdown menu
     * @param courseId - The ID of the course
     */
    async clickImportIntoClass(courseId: number): Promise<void> {
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_IMPORT_INTO_CLASS_OPTION.replace("{courseId}", courseId.toString()))
            .click();
    }

    /**
     * Click export to class for an activity
     * @param activityId - The ID of the activity
     */
    async clickExportToClass(activityId: number): Promise<void> {
        await this.page
            .getByTestId(UnitPlanner.DATA_TEST_ACTIVITY_EXPORT.replace("{activityId}", activityId.toString()))
            .click();
    }

    /**
     * Click the View More button for an unit
     * @param unitId - The ID of the unit
     * @param columnType - The type of the column: curriculum-overview, activities, rubric-capabilities
     */
    async clickViewMoreButton(unitId: number, columnType: string): Promise<void> {
        await this.page
            .getByTestId(
                UnitPlanner.DATA_TEST_VIEW_MORE.replace("{unitId}", unitId.toString()).replace(
                    "{columType}",
                    columnType,
                ),
            )
            .click();
    }
}

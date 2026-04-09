import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Locator, Page } from "@playwright/test";
import { expect } from "@playwright/test";
import Common from "../../Common";
import SummativePreview from "../lms/summativeReports/SummativePreview";

/**
 * Page object for the Admin > Lists > Qualifications page (/adminv2/lists/qualification).
 * The page allows superusers to manage the list of qualifications (IB/IBDP).
 *
 * Source references:
 * - library/Schoolbox/Admin/Lists/Controller.php (qualification config)
 * - library/Schoolbox/Admin/Lists/templates/vue/admin-list.vue
 * - library/Schoolbox/Admin/Lists/templates/vue/row/admin-list-row.vue
 * - library/Schoolbox/IB/Qualification/Mapper.php (table: lu_qualification)
 * - library/Schoolbox/IB/Qualification/Model.php (system purpose constants)
 */
export default class QualificationList {
    private tester: AcceptanceTester;

    private page: Page;

    private Common: Common;

    static readonly URL = "/adminv2/lists/qualification";

    /** Empty state element shown when no rows exist */
    static readonly DATA_TEST_EMPTY_STATE = "admin-list-empty-state";

    /** "Add New Row" anchor */
    static readonly DATA_TEST_ADD_ROW_BUTTON = "admin-list-add-row-button";

    /** Save submit input */
    static readonly DATA_TEST_SAVE_BUTTON = "admin-list-save-button";

    /** Each list row */
    static readonly SELECTOR_ROW = "li.list-item-container";

    /** Delete (bin) icon on each row */
    static readonly DATA_TEST_DELETE_ROW_BUTTON = "admin-list-row-delete-button-{rowId}";

    /** Drag-and-drop handle on each row */
    static readonly DATA_TEST_GRIP = "admin-list-row-grip";

    /** "Yes" confirm button inside the delete-row modal */
    static readonly DATA_TEST_DELETE_CONFIRM_BUTTON = "admin-list-confirm-delete";

    /** Inline validation error label rendered by admin-list-row.vue */
    static readonly SELECTOR_VALIDATION_ERROR = "label.error";

    /** Column header element */
    static readonly DATA_TEST_COLUMN_HEADER = "admin-list-header";

    /** Toast notification component */
    static readonly SELECTOR_TOAST = "[data-test='toast']";

    /** Visible label for the "Not Selected" System Purpose option */
    static readonly SYSTEM_PURPOSE_NOT_SELECTED = "Not Selected";

    /** Visible label for the Diploma Programme Year 2 option */
    static readonly SYSTEM_PURPOSE_DIPLOMA = "Diploma Programme Year 2 [DIPLOMA]";

    /** Visible label for the Diploma Programme Year 1 (Anticipated) option */
    static readonly SYSTEM_PURPOSE_ANTICIPATED = "Diploma Programme Year 1 [ANTICIPATED]";

    /** Visible label for the Diploma Course option */
    static readonly SYSTEM_PURPOSE_COURSE = "Diploma Course [COURSE]";

    /** Input field name for each row */
    static readonly DATA_TEST_ROW_INPUT_FIELD = "admin-list-row-input-field";

    /**
     * HTML maxlength attribute on the Name input field.
     * Source: Admin/Lists/Controller.php, qualification.fields.name.maxLength = 255.
     */
    static readonly NAME_MAX_LENGTH = 255;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
        this.Common = new Common(page);
    }

    /**
     * Navigates to the Qualifications list admin page and waits for data to load.
     */
    async goto(): Promise<void> {
        await this.page.goto(QualificationList.URL);
    }

    /**
     * Asserts the empty-state element is visible with the expected text.
     */
    async checkEmptyStateIsPresented(): Promise<void> {
        await expect(this.page.getByTestId(QualificationList.DATA_TEST_EMPTY_STATE)).toBeVisible();
        await expect(this.page.getByTestId(QualificationList.DATA_TEST_EMPTY_STATE)).toContainText(
            "The Qualifications list is empty.",
        );
    }

    /**
     * Asserts the column headers (ID, Name, System Purpose) are present in the list header.
     */
    async checkColumnsArePresented(): Promise<void> {
        const header = this.page.getByTestId(QualificationList.DATA_TEST_COLUMN_HEADER);
        await expect(header).toContainText("ID");
        await expect(header).toContainText("Name");
        await expect(header).toContainText("System Purpose");
    }

    /**
     * Clicks the "Add New Row" button to append a blank row to the list.
     */
    async clickAddNewRowButton(): Promise<void> {
        await this.page.getByTestId(QualificationList.DATA_TEST_ADD_ROW_BUTTON).click();
    }

    /**
     * Returns the current count of rows visible in the list.
     * @returns The number of rows
     */
    async getRowCount(): Promise<number> {
        return this.page.locator(QualificationList.SELECTOR_ROW).count();
    }

    /**
     * Returns a Locator for the row at the given 0-based index.
     * @param rowIndex - zero-based row index
     * @returns Locator for the row
     */
    private getRowLocator(rowIndex: number): Locator {
        return this.page.locator(QualificationList.SELECTOR_ROW).nth(rowIndex);
    }

    /**
     * Fills the Name text input for the row at the given index.
     * @param rowIndex - zero-based row index
     * @param name - value to fill in
     */
    async fillNameInRow(rowIndex: number, name: string): Promise<void> {
        await this.getRowLocator(rowIndex).getByTestId(QualificationList.DATA_TEST_ROW_INPUT_FIELD).fill(name);
    }

    /**
     * Selects a System Purpose option by its visible label for the row at the given index.
     * @param rowIndex - zero-based row index
     * @param optionLabel - the visible text of the option to select
     */
    async selectSystemPurposeInRow(rowIndex: number, optionLabel: string): Promise<void> {
        await this.getRowLocator(rowIndex).locator("select").selectOption({ label: optionLabel });
    }

    /**
     * Returns the visible text contents of all System Purpose <option> elements for the row at the given index.
     * @param rowIndex - zero-based row index
     * @returns Array of option label texts
     */
    async getSystemPurposeOptionsInRow(rowIndex: number): Promise<string[]> {
        return this.getRowLocator(rowIndex).locator("select option").allTextContents();
    }

    /**
     * Returns the current inputValue of the Name field for the row at the given index.
     * @param rowIndex - zero-based row index
     * @returns The current value of the Name field
     */
    async getRowNameAtIndex(rowIndex: number): Promise<string> {
        return this.getRowLocator(rowIndex).getByTestId(QualificationList.DATA_TEST_ROW_INPUT_FIELD).inputValue();
    }

    /**
     * Finds the 0-based index of the first row whose Name field equals the given value.
     * Returns -1 if no matching row is found.
     * @param name - the Name value to search for
     * @returns The zero-based row index, or -1 if not found
     */
    async getRowIndexByName(name: string): Promise<number> {
        const count = await this.getRowCount();
        for (let i = 0; i < count; i += 1) {
            const rowName = await this.getRowNameAtIndex(i);
            if (rowName === name) {
                return i;
            }
        }
        return -1;
    }

    /**
     * Clicks the Save (submit) button.
     */
    async clickSaveButton(): Promise<void> {
        await this.page.getByTestId(QualificationList.DATA_TEST_SAVE_BUTTON).click();
    }

    /**
     * Asserts the "Save successful" toast notification becomes visible and then hidden to ensure the save operation completed successfully.
     */
    async checkSaveSuccessToast(): Promise<void> {
        await expect(this.page.locator(QualificationList.SELECTOR_TOAST)).toBeVisible();
        await expect(this.page.locator(QualificationList.SELECTOR_TOAST)).not.toBeVisible();
    }

    /**
     * Clicks the delete (bin) icon for the row at the given index.
     * New (unsaved) rows are removed immediately; persisted rows open a confirmation modal.
     * @param rowId - the ID of the row to delete
     */
    async clickDeleteRowButton(rowId: string): Promise<void> {
        await this.page
            .getByTestId(QualificationList.DATA_TEST_DELETE_ROW_BUTTON.replace("{rowId}", rowId.toString()))
            .click();
    }

    /**
     * Clicks the "Yes" confirm button inside the delete-row modal.
     */
    async confirmDeleteRow(): Promise<void> {
        await this.page.getByTestId(QualificationList.DATA_TEST_DELETE_CONFIRM_BUTTON).click();
    }

    /**
     * Asserts that at least one row in the list contains the given Name value.
     * Uses toPass() to retry until the async row data is available.
     * @param name - the expected Name value
     */
    async checkRowWithNameIsPresented(name: string): Promise<void> {
        await expect(async () => {
            const index = await this.getRowIndexByName(name);
            expect(index).not.toBe(-1);
        }).toPass();
    }

    /**
     * Asserts that no row in the list contains the given Name value.
     * Uses toPass() to retry until the async row data is available.
     * @param name - the Name value that should not appear
     */
    async checkRowWithNameIsNotPresented(name: string): Promise<void> {
        await expect(async () => {
            const index = await this.getRowIndexByName(name);
            expect(index).toBe(-1);
        }).toPass();
    }

    /**
     * Asserts that a validation error label is visible inside the row at the given index.
     * @param rowIndex - zero-based row index
     */
    async checkValidationErrorIsShownForRow(rowIndex: number): Promise<void> {
        const row = this.getRowLocator(rowIndex);
        await expect(row.locator(QualificationList.SELECTOR_VALIDATION_ERROR)).toBeVisible();
        await expect(row.locator(QualificationList.SELECTOR_VALIDATION_ERROR)).toContainText("This field is required.");
    }

    /**
     * Get current order of list items
     * @returns - The order of qualification IDs
     */
    async getItemsOrder(): Promise<string[]> {
        const allActivityRowSelector = /^admin-list-row-/;
        const pageOrder = await this.page
            .getByTestId(allActivityRowSelector)
            .evaluateAll((activityRows) => activityRows.map((activityRow) => activityRow.getAttribute("data-row-id")));
        return pageOrder.filter((id): id is string => id !== null);
    }

    /**
     * drag and drop an item to a new position
     * @param itemIdToDrag - The ID of the item to drag to
     * @param itemIdToDragFrom - The ID of the item to drag from
     */
    async dragAndDropItem(itemIdToDrag: number, itemIdToDragFrom: number): Promise<void> {
        const itemRow = this.page.locator(`li[data-row-id="${itemIdToDrag}"]`);

        const dragHandle = itemRow.locator("div.grip.ui-sortable-handle");
        await dragHandle.scrollIntoViewIfNeeded();
        await dragHandle.hover();
        await this.page.mouse.down();

        const courseBuilderComponent = this.page.locator(`ul[class="information-list sortable ui-sortable"]`);

        // Move mouse to top of container, then wait for the reorder to happen
        await courseBuilderComponent.hover({ position: { x: 0, y: 30 } });

        this.tester.isGoingTo("keep dragging and wait for items to reorder");
        let newItemOrder = await this.getItemsOrder();
        const startTime = Date.now();
        while (
            newItemOrder.indexOf(itemIdToDrag.toString()) > newItemOrder.indexOf(itemIdToDragFrom.toString()) &&
            Date.now() - startTime < 5000 // Timeout after 5 seconds
        ) {
            newItemOrder = await this.getItemsOrder();
        }

        await this.page.mouse.up();

        await this.page.getByTestId("admin-list-save-button").click();
    }

    /**
     * Asserts that the Name input for the row at the given index has the expected HTML maxlength attribute.
     * @param rowIndex - zero-based row index
     */
    async checkNameMaxLengthAttribute(rowIndex: number): Promise<void> {
        await expect(this.getRowLocator(rowIndex).locator(`[data-test="admin-list-row-input-field"]`)).toHaveAttribute(
            "maxlength",
            String(QualificationList.NAME_MAX_LENGTH),
        );
    }

    /**
     * Asserts that the ID cell for the row at the given index is read-only
     * (rendered as a <p> element with no editable <input>).
     * Source: admin-list-row.vue uses field.readonly → renders <p> instead of <input>.
     * @param rowIndex - zero-based row index
     */
    async checkIdIsReadOnly(rowIndex: number): Promise<void> {
        const idCell = this.getRowLocator(rowIndex).locator(".column").first();
        await expect(idCell.locator("p")).toBeVisible();
        await expect(idCell.locator("input")).toHaveCount(0);
    }

    /**
     * Asserts that the System Purpose dropdown for the given row contains all expected options:
     * Not Selected, Diploma Programme Year 2 [DIPLOMA], Diploma Programme Year 1 [ANTICIPATED],
     * Diploma Course [COURSE].
     * @param rowIndex - zero-based row index
     */
    async checkSystemPurposeOptionsArePresented(rowIndex: number): Promise<void> {
        const options = await this.getSystemPurposeOptionsInRow(rowIndex);
        expect(options).toContain(QualificationList.SYSTEM_PURPOSE_NOT_SELECTED);
        expect(options).toContain(QualificationList.SYSTEM_PURPOSE_DIPLOMA);
        expect(options).toContain(QualificationList.SYSTEM_PURPOSE_ANTICIPATED);
        expect(options).toContain(QualificationList.SYSTEM_PURPOSE_COURSE);
    }

    /**
     * Drags the row at fromIndex to overlap with the row at toIndex using the grab handles.
     * Compatible with jQuery UI sortable used by admin-list.vue.
     * @param fromIndex - zero-based source row index
     * @param toIndex - zero-based target row index
     */
    async dragRowToPosition(fromIndex: number, toIndex: number): Promise<void> {
        const rows = this.page.locator(QualificationList.SELECTOR_ROW);
        const sourceGrip = rows.nth(fromIndex).getByTestId(QualificationList.DATA_TEST_GRIP);
        const targetGrip = rows.nth(toIndex).getByTestId(QualificationList.DATA_TEST_GRIP);
        await sourceGrip.dragTo(targetGrip);
        await this.page.waitForTimeout(1000); // wait for the DOM to update after drag-and-drop
    }
}

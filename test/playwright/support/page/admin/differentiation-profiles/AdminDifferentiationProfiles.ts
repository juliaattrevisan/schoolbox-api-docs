/**
 * Utility class for managing differentiation profiles admin settings
 */

import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class AdminDifferentiationProfiles {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly DATA_TEST_TOGGLE = "differentiation-profiles-toggle";

    static readonly DATA_TEST_TERMINOLOGY_HEADER = "differentiation-profiles-header-Terminology";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the differentiation profiles admin page
     */
    async navigateToPage(): Promise<void> {
        this.tester.isGoingTo("Visit the differentiation profiles admin page");
        await this.page.goto("/adminv2/differentiation/differentiation-profiles");
    }

    /**
     * Toggles the differentiation profiles on, validates terminology is visible, saves and verifies persistence
     */
    async toggleOn(): Promise<void> {
        this.tester.isGoingTo("Toggle the differentiation profiles on");
        await this.page.getByTestId("differentiation-profiles-toggle").click();
        this.tester.isGoingTo("Validate that the text 'Terminology' is now visible on the page");
        await expect(this.page.getByTestId("differentiation-profiles-header-Terminology")).toBeVisible();
        await this.clickSave();
        this.tester.isGoingTo("Refresh the page");
        await this.page.reload();
        this.tester.isGoingTo("Validate that the text 'Terminology' is still visible on the page after refresh");
        await expect(this.page.getByTestId("differentiation-profiles-header-Terminology")).toBeVisible();
        this.tester.isGoingTo("Verify that the checkbox is still checked");
        await expect(this.page.getByTestId("differentiation-profiles-toggle")).toBeChecked();
    }

    /**
     * Toggles the differentiation profiles off, validates terminology is not visible, saves and verifies persistence
     */
    async toggleOff(): Promise<void> {
        this.tester.isGoingTo("Toggle the differentiation profiles off");
        await this.page.getByTestId("differentiation-profiles-toggle").click();
        this.tester.isGoingTo("Validate that the text 'Terminology' is no longer visible on the page");
        await expect(this.page.getByTestId("differentiation-profiles-header-Terminology")).not.toBeVisible();
        await this.clickSave();
        this.tester.isGoingTo("Refresh the page");
        await this.page.reload();
        this.tester.isGoingTo("Validate that the text 'Terminology' is no longer visible on the page after refresh");
        await expect(this.page.getByTestId("differentiation-profiles-header-Terminology")).not.toBeVisible();
        this.tester.isGoingTo("Verify that the checkbox is still unchecked");
        await expect(this.page.getByTestId("differentiation-profiles-toggle")).not.toBeChecked();
    }

    /**
     * Renames a field by updating its input value, saving, and verifying persistence
     * @param fieldType - The field type (e.g., 'terminology')
     * @param fieldIndex - The field index (e.g., '1')
     * @param newValue - The new value to set in the field
     */
    async renameField(fieldType: string, fieldIndex: string, newValue: string): Promise<void> {
        const testId = `differentiation-profiles-input-${fieldType}-${fieldIndex}`;
        this.tester.isGoingTo(`Find the input with data-test attribute '${testId}'`);
        const fieldInput = this.page.getByTestId(testId);
        this.tester.isGoingTo(`Update the input text to '${newValue}'`);
        await fieldInput.clear();
        await fieldInput.fill(newValue);
        await this.clickSave();
        this.tester.isGoingTo("Refresh the page");
        await this.page.reload();
        this.tester.isGoingTo(`Ensure the text '${newValue}' is still visible`);
        await expect(this.page.getByTestId(testId)).toHaveValue(newValue);
    }

    /**
     * Disables a field by unchecking its checkbox, and verifying persistence
     * @param fieldType - The field type (e.g., 'terminology')
     * @param fieldIndex - The field index (e.g., '1')
     */
    async disableField(fieldType: string, fieldIndex: string): Promise<void> {
        const testId = `differentiation-profiles-input-${fieldType}-${fieldIndex}`;
        this.tester.isGoingTo(`Find the input with data-test attribute '${testId}'`);
        const checkbox = this.page.getByTestId(testId);
        const label = this.page.getByTestId(`${testId}-label`);
        this.tester.isGoingTo(`Uncheck the checkbox (by clicking the label)`);
        await label.click();
        await this.clickSave();
        this.tester.isGoingTo("Refresh the page");
        await this.page.reload();
        this.tester.isGoingTo(`Ensure the checkbox is unchecked`);
        await expect(checkbox).not.toBeChecked();
    }

    /**
     * Drags a field to the top position by handle ID, saves, and verifies the field is in first position
     * @param handleId - The drag handle ID (e.g., '7')
     * @param containerSuffix - The container suffix (e.g., 'details')
     * @param fieldName - The field name to verify in first position (e.g., 'Goals')
     */
    async dragToTop(handleId: string, containerSuffix: string, fieldName: string): Promise<void> {
        this.tester.isGoingTo(`grab the drag handle for the ${fieldName} field`);
        const dragHandle = this.page.getByTestId(`differentiation-profiles-drag-handle-${handleId}`);
        await dragHandle.scrollIntoViewIfNeeded();
        await dragHandle.hover();
        await this.page.mouse.down();
        this.tester.isGoingTo(`drag the ${fieldName} field upwards to reorder`);
        const container = this.page.getByTestId(`differentiation-profiles-fieldset-${containerSuffix}`);
        const box = await container.boundingBox();
        if (box) {
            await this.page.mouse.move(box.x, box.y + 10, {
                steps: 30,
            });
        }
        this.tester.isGoingTo("release the mouse to drop the field in the new position");
        await this.page.mouse.up();
        await this.clickSave();
        this.tester.isGoingTo("Refresh the page");
        await this.page.reload();
        this.tester.isGoingTo(`Ensure the ${fieldName} field is now in the first position`);
        const firstField = container.locator("li").first();
        await expect(firstField).toContainText(fieldName);
    }

    /**
     * Clicks the save button and waits for the success message
     */
    async clickSave(): Promise<void> {
        this.tester.isGoingTo("Select the save button");
        await this.page.getByRole("button", { name: "Save" }).click();
        this.tester.isGoingTo("Wait for the success message to appear");
        await expect(this.page.locator("text=Differentiation profiles successfully updated")).toBeVisible();
    }
}

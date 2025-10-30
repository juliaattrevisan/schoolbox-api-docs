/**
 * Utility class for managing differentiation profiles admin settings
 * linked to: library/Schoolbox/Admin/DifferentiationProfiles/templates/vue/differentiation-profiles.vue
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
        this.tester.isGoingTo("Visit the differentiation profiles page");
        await this.page.goto("/adminv2/pastoral/differentiation-profiles");
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
     * Clicks the save button and waits for the success message
     */
    async clickSave(): Promise<void> {
        this.tester.isGoingTo("Select the save button");
        await this.page.getByRole("button", { name: "Save" }).click();
        this.tester.isGoingTo("Wait for the success message to appear");
        await expect(this.page.locator("text=Differentiation profiles successfully updated")).toBeVisible();
    }
}

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
     * Toggles the differentiation profiles on, validates terminology is visible, saves and verifies persistence
     */
    async toggleOn(): Promise<void> {
        this.tester.isGoingTo(
            "Toggle the checkbox input with the data-test attribute 'differentiation-profiles-toggle'",
        );
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
     * Clicks the save button and waits for the success message
     */
    async clickSave(): Promise<void> {
        this.tester.isGoingTo("Select the save button");
        await this.page.getByRole("button", { name: "Save" }).click();
        this.tester.isGoingTo("Wait for the success message to appear");
        await expect(this.page.locator("text=Differentiation profiles successfully updated")).toBeVisible();
    }
}

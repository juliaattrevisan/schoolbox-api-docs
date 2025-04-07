import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";

export default class ProfileEditor {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/adminv2/profile-editor";

    static readonly SELECTOR_NAME_DISPLAY_FIELD = "#pe-name-format";

    static readonly SELECTOR_UPDATE_BUTTON = ".button.submit";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the profile editor page
     */
    async goto(): Promise<void> {
        await this.page.goto(ProfileEditor.URL);
    }

    /**
     * Click an element based on role type
     * @param roleType - The user type: student, staff, parent
     */
    async clickRoleType(roleType: string): Promise<void> {
        await this.page.getByTestId(`pe-role-type-${roleType}`).click();
    }

    /**
     * Type in the field name display
     * @param fieldName - The field name
     */
    async typeFieldName(fieldName: string): Promise<void> {
        await this.page.locator(ProfileEditor.SELECTOR_NAME_DISPLAY_FIELD).fill(fieldName);
    }

    /**
     * Select & Clear the field name
     */
    async clearFieldName(): Promise<void> {
        await this.page.locator(ProfileEditor.SELECTOR_NAME_DISPLAY_FIELD).click();
        await this.page.locator(ProfileEditor.SELECTOR_NAME_DISPLAY_FIELD).clear();
    }

    /**
     * Click the update button
     */
    async clickUpdateButton(): Promise<void> {
        await this.page.locator(ProfileEditor.SELECTOR_UPDATE_BUTTON).click();
    }

    /**
     * Click the cog of a field
     * @param roleType - The user type: student, staff, parent
     * @param fieldName - The field name
     */
    async clickFieldCog(roleType: string, fieldName: string): Promise<void> {
        await this.page.getByTestId(`pe-${roleType}-fields-active-item-${fieldName}-settings`).click();
    }

    /**
     * Click the field visibility or availability checkbox
     * @param roleType - The user type: student, staff, parent
     * @param fieldName - The field name
     * @param option - The option to click: visibility or availability
     * @param optionValue - The option value to click: student, 1
     */
    async clickFieldCheckbox(roleType: string, fieldName: string, option: string, optionValue: string): Promise<void> {
        await this.page
            .getByTestId(`pe-${roleType}-fields-active-item-${fieldName}-setting-${option}-option-${optionValue}`)
            .click();
    }

    /**
     * Click the update button of a field
     * @param roleType - The user type: student, staff, parent
     * @param fieldName - The field name
     */
    async clickFieldUpdateButton(roleType: string, fieldName: string): Promise<void> {
        await this.page.getByTestId(`pe-${roleType}-fields-active-item-${fieldName}-settings-confirm`).click();
        await expect(this.page.getByTestId("toast")).toHaveAttribute("data-test-is-success", "true");
        await expect(this.page.getByTestId("toast")).not.toBeVisible();
    }

    /**
     * Click the available field to add in the active field
     * @param roleType - The user type: student, staff, parent
     * @param fieldName - The field name
     */
    async clickAvailableField(roleType: string, fieldName: string): Promise<void> {
        await this.page.getByTestId(`pe-${roleType}-fields-available-item-${fieldName}`).click();
    }

    /**
     * Click a specified fields tab
     * @param tabName - The name of the tab to click: External Fields or Internal Fields
     */
    async clickFieldsTab(tabName: "External Fields" | "Internal Fields"): Promise<void> {
        await this.page.getByText(tabName).click();
    }

    /**
     * Click the cog of a tabs
     * @param roleType - The user type: student, staff, parent
     * @param tabName - The tab name
     */
    async clickTabCog(roleType: string, tabName: string): Promise<void> {
        await this.page.getByTestId(`pe-${roleType}-tabs-tab-${tabName}-settings`).click();
    }

    /**
     * Click the field visibility or availability checkbox
     * @param roleType - The user type: student, staff, parent
     * @param tabName - The tab name
     * @param option - The option to click: visibility or availability
     * @param optionValue - The option value to click: self, 1
     */
    async clickTabCheckbox(roleType: string, tabName: string, option: string, optionValue: string): Promise<void> {
        await this.page
            .getByTestId(`pe-${roleType}-tabs-tab-${tabName}-setting-${option}-option-${optionValue}`)
            .click();
    }

    /**
     * Click the update button of a tab
     * @param roleType - The user type: student, staff, parent
     * @param tabName - The tab name
     */
    async clickTabUpdateButton(roleType: string, tabName: string): Promise<void> {
        await this.page.getByTestId(`pe-${roleType}-tabs-tab-${tabName}-settings-confirm`).click();
        await expect(this.page.getByTestId("toast")).toHaveAttribute("data-test-is-success", "true");
        await expect(this.page.getByTestId("toast")).not.toBeVisible();
    }
}

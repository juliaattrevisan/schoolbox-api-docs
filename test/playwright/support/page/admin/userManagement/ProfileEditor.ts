import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";

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
        await this.page.waitForLoadState("load");
    }

    /**
     * Clicks on the specified role type inside the profile editor container.
     * @param roleType - The role type to click (e.g., "Parent", "Staff", "Student")
     */
    async clickRoleType(roleType: string): Promise<void> {
        const tabName = this.page.locator("#profile-editor-container").getByText(roleType);
        await tabName.scrollIntoViewIfNeeded();
        await tabName.click();
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
     * @param fieldName - The field name
     * @param roleType - The user type: student, staff, parent
     */

    async clickFieldCog(roleType: string, fieldName: string): Promise<void> {
        await this.page.getByTestId(`pe-${roleType}-fields-active-item-${fieldName}-settings`).click();
    }
    /**
     * Click the field visibility or availability checkbox
     * @param fieldName - The field name
     * @param roleType - The user type: student, staff, parent
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
     * @param fieldName - The field name
     * @param roleType - The user type: student, staff, parent
     */

    async clickFieldUpdateButton(roleType: string, fieldName: string): Promise<void> {
        await this.page.getByTestId(`pe-${roleType}-fields-active-item-${fieldName}-settings-confirm`).click();
    }

    /**
     * Click the available field to add in the active field
     * @param fieldName - The field name
     * @param roleType - The user type: student, staff, parent
     */

    async clickAvailableField(roleType: string, fieldName: string): Promise<void> {
        await this.page.getByTestId(`pe-${roleType}-fields-available-item-${fieldName}`).click();
        // await this.page.getByTestId(`pe-parent-fields-available-item-campusCollection`).click();
    }

    /**
     * Click a specified fields tab
     * @param tabName - The name of the tab to click: External Fields or Internal Fields
     */
    async clickFieldsTab(tabName: "External Fields" | "Internal Fields"): Promise<void> {
        await this.page.getByText(tabName).click();
    }
    /**
     * Click the cog of a field
     * @param fieldName - The field name
     * @param roleType - The user type: student, staff, parent
     */

    async scrolltoFind(roleType: string, fieldName: string): Promise<void> {
        await this.page.getByTestId(`pe-${roleType}-fields-active-item-${fieldName}-settings`).scrollIntoViewIfNeeded();
    }
}

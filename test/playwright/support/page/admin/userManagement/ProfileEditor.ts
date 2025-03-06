import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";

export default class ProfileEditor {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/adminv2/profile-editor#{type}";

    static readonly SELECTOR_NAME_DISPLAY_FIELD = "#pe-name-format";

    static readonly SELECTOR_UPDATE_BUTTON = ".button.submit";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the profile editor page
     * @param type - The user type: student, staff, parent
     */
    async goto(type: string): Promise<void> {
        await this.page.goto(ProfileEditor.URL.replace("{type}", type));
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
     * @param userType - The user type: student, staff, parent
     */

    async clickFieldCog(fieldName: string, userType: string): Promise<void> {
        await this.page.getByTestId(`pe-${userType}-fields-active-item-${fieldName}-settings`).click();
    }
    /**
     * Click the field visibility or availability checkbox
     * @param fieldName - The field name
     * @param userType - The user type: student, staff, parent
     * @param option - The option to click: visibility or availability
     * @param optionValue - The option value to click: student, 1
     */

    async clickFieldCheckbox(fieldName: string, userType: string, option: string, optionValue: string): Promise<void> {
        await this.page
            .getByTestId(`pe-${userType}-fields-active-item-${fieldName}-setting-${option}-option-${optionValue}`)
            .click();
    }

    /**
     * Click the update button of a field
     * @param fieldName - The field name
     * @param userType - The user type: student, staff, parent
     */

    async clickFieldUpdateButton(fieldName: string, userType: string): Promise<void> {
        await this.page.getByTestId(`pe-${userType}-fields-active-item-${fieldName}-settings-confirm`).click();
    }

    /**
     * Click the available field to add in the active field
     * @param fieldName - The field name
     * @param userType - The user type: student, staff, parent
     */

    async clickAvailableField(fieldName: string, userType: string): Promise<void> {
        await this.page.getByTestId(`pe-${userType}-fields-available-item-${fieldName}`).click();
    }
}

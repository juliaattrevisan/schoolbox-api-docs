/**
 * Utility class for interacting with the Skin Config admin page
 * linked primarily to: library/Schoolbox/Admin/templates/vue/skinConfig/skin-config.vue
 */

import type { Locator, Page } from "@playwright/test";

export default class SkinConfig {
    static readonly URL = "/adminv2/setting/design/Skin%20Config";

    static readonly PANTRY_BUTTONS_URL = "/pantry/buttons";

    private page: Page;

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to the Skin Config admin page.
     * @returns A promise that resolves when navigation completes.
     */
    async goto(): Promise<void> {
        await this.page.goto(SkinConfig.URL);
    }

    /**
     * Gets a Skin Config field by its configuration name.
     * @param configName - The name of the Skin Config setting.
     * @returns The field locator.
     */
    getField(configName: string): Locator {
        return this.page.getByTestId(`skin-config-${configName}-field`);
    }

    /**
     * Gets the validation error for a Skin Config field.
     * @param configName - The name of the Skin Config setting.
     * @returns The validation error locator.
     */
    getValidationError(configName: string): Locator {
        return this.page.getByTestId(`skin-config-${configName}-error`);
    }

    /**
     * Sets a colour field using the colour picker.
     * @param configName - The name of the Skin Config colour setting.
     * @param value - The CSS colour value to enter.
     * @returns A promise that resolves when the colour picker is closed.
     */
    async setColour(configName: string, value: string): Promise<void> {
        await this.getField(configName).click();
        await this.page.getByRole("textbox", { name: "Color value field" }).fill(value);
        await this.page.getByRole("button", { name: "Close color picker" }).click();
    }

    /**
     * Selects an option for a Skin Config field.
     * @param configName - The name of the Skin Config setting.
     * @param label - The visible label of the option to select.
     * @returns A promise that resolves when the option is selected.
     */
    async selectOption(configName: string, label: string): Promise<void> {
        await this.getField(configName).selectOption({ label });
    }

    /**
     * Sets the Custom CSS field value.
     * @param value - The CSS to enter.
     * @returns A promise that resolves when the field is filled.
     */
    async setCustomCss(value: string): Promise<void> {
        await this.getField("skin_sonar_custom_css").fill(value);
    }

    /**
     * Saves the current Skin Config values.
     * @returns A promise that resolves when the save button is clicked.
     */
    async save(): Promise<void> {
        await this.page.getByTestId("skin-config-save-submit").click();
    }

    /**
     * Undoes the current unsaved Skin Config changes.
     * @returns A promise that resolves when the undo button is clicked.
     */
    async undo(): Promise<void> {
        await this.page.getByTestId("skin-config-undo-changes").click();
    }

    /**
     * Gets the successful save notification.
     * @returns The success toast locator.
     */
    getSaveSuccessToast(): Locator {
        return this.page.getByTestId("toast").filter({ hasText: "Skin config successfully updated" });
    }

    /**
     * Gets a computed CSS custom property from the document root.
     * @param property - The CSS custom property name.
     * @returns The trimmed computed property value.
     */
    async getCssCustomProperty(property: string): Promise<string> {
        return this.page.evaluate(
            (customProperty) => getComputedStyle(document.documentElement).getPropertyValue(customProperty).trim(),
            property,
        );
    }

    /**
     * Gets the computed body background colour.
     * @returns The computed CSS background colour.
     */
    async getBodyBackgroundColour(): Promise<string> {
        return this.page.evaluate(() => getComputedStyle(document.body).backgroundColor);
    }

    /**
     * Navigates to the Pantry buttons page.
     * @returns A promise that resolves when navigation completes.
     */
    async gotoPantryButtons(): Promise<void> {
        await this.page.goto(SkinConfig.PANTRY_BUTTONS_URL);
    }

    /**
     * Gets the Pantry Submit button.
     * @returns The Submit button locator.
     */
    getPantrySubmitButton(): Locator {
        return this.page.getByTestId("skin-config-pantry-submit-button");
    }

    /**
     * Gets the Pantry Load more button.
     * @returns The Load more button locator.
     */
    getPantryLoadMoreButton(): Locator {
        return this.page.getByTestId("skin-config-pantry-load-more-button");
    }
}

import type { Locator, Page } from "@playwright/test";

export default class SkinConfig {
    static readonly URL = "/adminv2/setting/design/Skin%20Config";

    static readonly PANTRY_BUTTONS_URL = "/pantry/buttons";

    private page: Page;

    constructor(page: Page) {
        this.page = page;
    }

    async goto(): Promise<void> {
        await this.page.goto(SkinConfig.URL);
    }

    getField(configName: string): Locator {
        return this.page.getByTestId(`skin-config-${configName}-field`);
    }

    getValidationError(configName: string): Locator {
        return this.page.getByTestId(`skin-config-${configName}-error`);
    }

    async setColour(configName: string, value: string): Promise<void> {
        await this.getField(configName).click();
        await this.page.getByRole("textbox", { name: "Color value field" }).fill(value);
        await this.page.getByRole("button", { name: "Close color picker" }).click();
    }

    async selectOption(configName: string, label: string): Promise<void> {
        await this.getField(configName).selectOption({ label });
    }

    async setCustomCss(value: string): Promise<void> {
        await this.getField("skin_sonar_custom_css").fill(value);
    }

    async save(): Promise<void> {
        await this.page.getByTestId("skin-config-save-submit").click();
    }

    async undo(): Promise<void> {
        await this.page.getByTestId("skin-config-undo-changes").click();
    }

    getSaveSuccessToast(): Locator {
        return this.page.getByTestId("toast").filter({ hasText: "Skin config successfully updated" });
    }

    async getCssCustomProperty(property: string): Promise<string> {
        return this.page.evaluate(
            (customProperty) => getComputedStyle(document.documentElement).getPropertyValue(customProperty).trim(),
            property,
        );
    }

    async getBodyBackgroundColour(): Promise<string> {
        return this.page.evaluate(() => getComputedStyle(document.body).backgroundColor);
    }

    async gotoPantryButtons(): Promise<void> {
        await this.page.goto(SkinConfig.PANTRY_BUTTONS_URL);
    }

    getPantrySubmitButton(): Locator {
        return this.page.getByTestId("skin-config-pantry-submit-button");
    }

    getPantryLoadMoreButton(): Locator {
        return this.page.getByTestId("skin-config-pantry-load-more-button");
    }
}

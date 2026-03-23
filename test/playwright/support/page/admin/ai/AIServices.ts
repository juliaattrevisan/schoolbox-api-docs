import type { Locator, Page } from "@playwright/test";
import type { AiFeature } from "@schoolboxLibrary/Admin/OpenAI/Usage/templates/payload";
import { expect } from "@schoolboxTest/playwright/support/integrations";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export enum AiProvider {
    OFF = "Off",
    OPENAI = "OpenAI",
    AZURE = "Azure",
}

export default class AIServices {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly URL = "/adminv2/ai-services";

    static readonly DATA_TEST_AI_FEATURE = "ai-feature"; // Append "-{feature}"

    static readonly DATA_TEST_AI_FEATURE_OPTION_SELECT_PROVIDER = "ai-feature-option-select-provider";
    static readonly DATA_TEST_AI_FEATURE_OPTION_INPUT_AI_KEY = "ai-feature-option-input-ai_key";
    static readonly DATA_TEST_AI_FEATURE_OPTION_SELECT_MODEL = "ai-feature-option-select-model"; // OpenAI only
    static readonly DATA_TEST_AI_FEATURE_OPTION_INPUT_RESOURCE_NAME = "ai-feature-option-input-resource_name"; // Azure only
    static readonly DATA_TEST_AI_FEATURE_OPTION_INPUT_DEPLOYMENT_ID = "ai-feature-option-input-deployment_id"; // Azure only
    static readonly DATA_TEST_AI_FEATURE_OPTION_TEXTAREA_SYSTEM_PROMPT = "ai-feature-option-textarea-system_prompt";
    static readonly DATA_TEST_AI_FEATURE_OPTION_CHECKBOX_AI_MODERATION = "ai-feature-option-checkbox-ai_moderation";

    static readonly DATA_TEST_SAVE_ADMIN_SETTINGS_SUBMIT = "save-admin-settings-submit";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * Navigates to the AI Services admin page
     */
    public async goto(): Promise<void> {
        await this.page.goto(AIServices.URL);
    }

    /**
     * Locate a particular AI feature section on the page
     * @param feature - the AI feature to locate
     * @returns Locator
     */
    public locateFeature(feature: AiFeature): Locator {
        return this.page.getByTestId(`${AIServices.DATA_TEST_AI_FEATURE}-${feature}`);
    }

    /**
     * Verify that a particular Provider available for a feature
     * @param feature - Locator for the particular feature
     * @param provider - the provider to verify
     */
    public static async verifyFeatureSupportsProvider(feature: Locator, provider: AiProvider): Promise<void> {
        const providerSelect = feature.getByTestId(AIServices.DATA_TEST_AI_FEATURE_OPTION_SELECT_PROVIDER);
        await expect(providerSelect).toBeVisible();
        const options = providerSelect.locator("option");
        await expect(options.filter({ hasText: provider })).toHaveCount(1);
    }

    /**
     * Verify that a particular Provider is NOT available for a feature
     * @param feature - Locator for the particular feature
     * @param provider - the provider to verify is not available
     */
    public static async verifyFeatureDoesNotSupportProvider(feature: Locator, provider: AiProvider): Promise<void> {
        const providerSelect = feature.getByTestId(AIServices.DATA_TEST_AI_FEATURE_OPTION_SELECT_PROVIDER);
        await expect(providerSelect).toBeVisible();
        const options = providerSelect.locator("option");
        await expect(options.filter({ hasText: provider })).toHaveCount(0);
    }

    /**
     * Verify that a particular Provider available for a feature
     * @param feature - Locator for the particular feature
     * @param provider - the provider to verify
     */
    public static async verifyFeatureUsesProvider(feature: Locator, provider: AiProvider): Promise<void> {
        const providerSelect = feature.getByTestId(AIServices.DATA_TEST_AI_FEATURE_OPTION_SELECT_PROVIDER);
        await expect(providerSelect).toHaveValue(provider);
    }

    /**
     * Select a provider for a feature
     * @param feature - Locator for the particular feature
     * @param provider - the provider to select
     */
    public static async selectProviderForFeature(feature: Locator, provider: AiProvider): Promise<void> {
        const providerSelect = feature.getByTestId(AIServices.DATA_TEST_AI_FEATURE_OPTION_SELECT_PROVIDER);
        await providerSelect.selectOption({ label: provider });
    }

    /**
     * Wait for a particular feature to be enabled
     * This is useful for waiting for the settings to properly save
     * @param feature - the feature to wait for
     */
    public async waitForFeatureToBeEnabled(feature: AiFeature): Promise<void> {
        await this.page.waitForFunction((_feature) => window.sbProps.aiFeatures[_feature]?.enabled === true, feature);
        const featureEnabled = await this.page.evaluate(
            (_feature) => window.sbProps.aiFeatures[_feature]?.enabled,
            feature,
        );
        expect(featureEnabled).toBe(true);
    }

    /**
     * Fill in the AI key input for a feature
     * @param feature - Locator for the particular feature
     */
    public static async fillOpenAIKeyForFeature(feature: Locator): Promise<void> {
        const openAIKey = process.env.OPENAI_API_TESTING_KEY;
        if (!openAIKey) {
            throw new Error("process.env.OPENAI_API_TESTING_KEY is not set");
        }

        await feature.getByTestId(AIServices.DATA_TEST_AI_FEATURE_OPTION_INPUT_AI_KEY).fill(openAIKey);
    }

    /**
     * Save the AI Services settings by clicking the save button
     */
    public async save(): Promise<void> {
        await this.page.getByTestId(AIServices.DATA_TEST_SAVE_ADMIN_SETTINGS_SUBMIT).click();
    }
}

import { AiFeature } from "@schoolboxLibrary/Admin/OpenAI/Usage/templates/payload";
import { test } from "@schoolboxTest/playwright/support/integrations";
import AIServices, { AiFeatureConfigMap, AiProvider } from "@schoolboxTest/playwright/support/page/admin/ai/AIServices";

const planEnabledFeatures = [
    AiFeature.AI_MODERATION_AGENT,
    AiFeature.CHAT,
    AiFeature.DIALOG_AGENT,
    AiFeature.DIFFERENTIATION,
    AiFeature.IMAGE,
    AiFeature.LEARNING_ACTIVITY_CUSTOMISATION,
    AiFeature.LESSON_PLAN,
    AiFeature.RUBRIC_GENERATION,
] as const;

type PlanEnabledFeature = (typeof planEnabledFeatures)[number];

test.describe("Admin AI Services Settings", () => {
    /// Keep a copy of the initial AI settings so they can be reset after the tests are done
    const initialSettings = Object.values(AiFeature).reduce(
        (acc, feature) => {
            acc[feature] = { config: null, enabled: null };
            return acc;
        },
        {} as Record<AiFeature, { config: string | null; enabled: string | null }>,
    );

    test.beforeAll(async ({ db }) => {
        for (const feature of Object.values(AiFeature)) {
            const configName = AiFeatureConfigMap[feature];
            initialSettings[feature] = {
                config: (await db.grabFromDatabase("config", "value", { name: configName })) as string,
                enabled: (await db.grabFromDatabase("config", "value", { name: `${configName}_enabled` })) as string,
            };
        }

        // Force all AI features to be enabled, the intent of these tests is to verify parts of the settings page and not the feature availability itself
        for (const feature of Object.values(AiFeature)) {
            const enabled = initialSettings[feature].enabled === "true" || initialSettings[feature].enabled === "1";
            if (!enabled) {
                await db.updateInDatabase("config", { value: "1" }, { name: `${AiFeatureConfigMap[feature]}_enabled` });
            }
        }
    });

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("supported AI providers for plan-enabled features", async ({ page, tester }) => {
        const aiServices = new AIServices(page, tester);
        await aiServices.goto();

        const featuresWithOpenAI: Record<PlanEnabledFeature, boolean> = {
            [AiFeature.AI_MODERATION_AGENT]: true,
            [AiFeature.CHAT]: true,
            [AiFeature.DIALOG_AGENT]: true,
            [AiFeature.DIFFERENTIATION]: true,
            [AiFeature.IMAGE]: true,
            [AiFeature.LEARNING_ACTIVITY_CUSTOMISATION]: true,
            [AiFeature.LESSON_PLAN]: true,
            [AiFeature.RUBRIC_GENERATION]: true,
        };

        const featuresWithAzure: Record<PlanEnabledFeature, boolean> = {
            [AiFeature.AI_MODERATION_AGENT]: true,
            [AiFeature.CHAT]: true,
            [AiFeature.DIALOG_AGENT]: true,
            [AiFeature.DIFFERENTIATION]: true,
            [AiFeature.IMAGE]: false,
            [AiFeature.LEARNING_ACTIVITY_CUSTOMISATION]: true,
            [AiFeature.LESSON_PLAN]: true,
            [AiFeature.RUBRIC_GENERATION]: true,
        };

        for (const featureKey of planEnabledFeatures) {
            const feature = aiServices.locateFeature(featureKey);

            if (featuresWithOpenAI[featureKey]) {
                await AIServices.verifyFeatureSupportsProvider(feature, AiProvider.OPENAI);
            } else {
                await AIServices.verifyFeatureDoesNotSupportProvider(feature, AiProvider.OPENAI);
            }

            if (featuresWithAzure[featureKey]) {
                await AIServices.verifyFeatureSupportsProvider(feature, AiProvider.AZURE);
            } else {
                await AIServices.verifyFeatureDoesNotSupportProvider(feature, AiProvider.AZURE);
            }
        }
    });

    test("enable Text Chat with OpenAI and save settings", async ({ page, tester }) => {
        const aiServices = new AIServices(page, tester);
        await aiServices.goto();

        const chatFeature = aiServices.locateFeature(AiFeature.CHAT);
        await AIServices.verifyFeatureUsesProvider(chatFeature, AiProvider.OFF);

        await AIServices.selectProviderForFeature(chatFeature, AiProvider.OPENAI);
        await AIServices.fillOpenAIKeyForFeature(chatFeature);
        await aiServices.save();

        tester.isGoingTo("wait for the feature to be enabled");
        await aiServices.waitForFeatureToBeEnabled(AiFeature.CHAT);
        await AIServices.verifyFeatureUsesProvider(chatFeature, AiProvider.OPENAI);
    });

    /**
     * Restore all original AI settings after tests have completed
     */
    test.afterAll(async ({ db }) => {
        for (const feature of Object.values(AiFeature)) {
            const configName = AiFeatureConfigMap[feature];
            await db.updateInDatabase("config", { value: initialSettings[feature].config }, { name: configName });
            await db.updateInDatabase(
                "config",
                { value: initialSettings[feature].enabled },
                { name: `${configName}_enabled` },
            );
        }
    });
});

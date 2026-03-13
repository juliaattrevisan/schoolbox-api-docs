import { AiFeature } from "@schoolboxLibrary/Admin/OpenAI/Usage/templates/payload";
import { test } from "@schoolboxTest/playwright/support/integrations";
import AIServices, { AiProvider } from "@schoolboxTest/playwright/support/page/admin/ai/AIServices";

test.describe("Admin AI Services Settings", () => {
    // Keep a copy of the initial AI settings so they can be reset after the tests are done
    const initialSettings: Record<string, string | null> = {
        ai_text_chat: null,
    };

    test.beforeAll(async ({ db }) => {
        for (const setting of Object.keys(initialSettings)) {
            initialSettings[setting] = (await db.grabFromDatabase("config", "value", { name: setting })) as string;
        }
    });

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("supported AI providers", async ({ page, tester }) => {
        const aiServices = new AIServices(page, tester);
        await aiServices.goto();

        const featuresWithOpenAI: Record<AiFeature, boolean> = {
            [AiFeature.AI_MODERATION_AGENT]: true,
            [AiFeature.CHAT]: true,
            [AiFeature.DIALOG_AGENT]: true,
            [AiFeature.DIFFERENTIATION]: true,
            [AiFeature.IMAGE]: true,
            [AiFeature.LEARNING_ACTIVITY_CUSTOMISATION]: true,
            [AiFeature.LESSON_PLAN]: true,
        };

        const featuresWithAzure: Record<AiFeature, boolean> = {
            [AiFeature.AI_MODERATION_AGENT]: true,
            [AiFeature.CHAT]: true,
            [AiFeature.DIALOG_AGENT]: true,
            [AiFeature.DIFFERENTIATION]: true,
            [AiFeature.IMAGE]: false,
            [AiFeature.LEARNING_ACTIVITY_CUSTOMISATION]: true,
            [AiFeature.LESSON_PLAN]: true,
        };

        for (const featureKey of Object.values(AiFeature)) {
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
        for (const [setting, value] of Object.entries(initialSettings)) {
            await db.updateInDatabase("config", { value }, { name: setting });
        }
    });
});

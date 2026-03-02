import { AiFeature } from "@schoolboxLibrary/Admin/OpenAI/Usage/templates/payload";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import AIServices, { AiProvider } from "@schoolboxTest/playwright/support/page/admin/ai/AIServices";
import Common from "@schoolboxTest/playwright/support/page/Common";
import DialogAgentConfigure from "@schoolboxTest/playwright/support/page/dialogAgent/DialogAgentConfigure";
import DialogAgentModify from "@schoolboxTest/playwright/support/page/dialogAgent/DialogAgentModify";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

const DATA_TEST_COMPONENT_DIALOGAGENT_EMPTY_CREATE_BUTTON = "component-dialogAgent-empty-create-button";

const EMPTY_STATE_MESSAGE_AI_DISABLED = "The Discussion feature is currently disabled";
const COMPONENT_NAME = "Discussions"; // Terminology for Dialog Agent is "Discussions"
const ACTIVITY_NAME = "Anteresia Stimsoni";

test.describe("Dialog Agent Homepage Component", () => {
    // Keep a copy of the initial AI settings so they can be reset after the tests are done
    const initialSettings: Record<string, string | null> = {
        ai_text_chat: null,
    };

    test.beforeAll(async ({ db }) => {
        for (const setting of Object.keys(initialSettings)) {
            initialSettings[setting] = (await db.grabFromDatabase("config", "value", { name: setting })) as string;
        }
    });

    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });

    let componentId: number;

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("add the component, validate empty state when AI is disabled", async ({ tester, page }) => {
        const homepage = new Homepage(tester, page);

        tester.isGoingTo("Homepage, open tab panel, click components tab");
        await homepage.goto(Common.CLASS_ID_9AENG);

        tester.isGoingTo("Add Discussions (Dialog Agent) component");
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_LEARNING, COMPONENT_NAME);
        const component = homepage.locateComponentByName(COMPONENT_NAME);
        componentId = await Homepage.getComponentId(component);

        tester.isGoingTo(
            "expect to see an empty state message indicating that the feature is disabled, and no create button",
        );
        await expect(component).toContainText(EMPTY_STATE_MESSAGE_AI_DISABLED);
        await expect(component.getByTestId(DATA_TEST_COMPONENT_DIALOGAGENT_EMPTY_CREATE_BUTTON)).not.toBeVisible();
    });

    test("enable AI feature, create a new discussion via the empty state", async ({ tester, page }) => {
        tester.isGoingTo("Enable the Dialog Agent feature in AI Services");
        const aiServices = new AIServices(page, tester);
        await aiServices.goto();

        const dialogAgentFeature = aiServices.locateFeature(AiFeature.DIALOG_AGENT);
        await AIServices.selectProviderForFeature(dialogAgentFeature, AiProvider.OPENAI);
        await AIServices.fillOpenAIKeyForFeature(dialogAgentFeature);
        await aiServices.save();

        tester.isGoingTo("create a new discussion via the empty state of the Dialog Agent component");
        const homepage = new Homepage(tester, page);
        const component = homepage.locateComponentByName(COMPONENT_NAME);
        await component.getByTestId(DATA_TEST_COMPONENT_DIALOGAGENT_EMPTY_CREATE_BUTTON).click();

        tester.isGoingTo("create a new activity");
        const assessmentForm = new AssessmentForm(tester, page);
        const dialogAgentModify = new DialogAgentModify(page, tester);
        const dialogAgentConfigure = new DialogAgentConfigure(page, tester);
        await assessmentForm.setTitle(ACTIVITY_NAME);
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_HOMEWORK);
        await dialogAgentModify.selectScenario("Self-Reflection");
        await assessmentForm.clickCreate();
        await dialogAgentConfigure.assertIsOnConfigurePage(ACTIVITY_NAME);
    });

    test("view activity in component", async ({ tester, page }) => {
        const homepage = new Homepage(tester, page);
        const component = homepage.locateComponentByName(COMPONENT_NAME);

        const allAssessmentsSelector = new RegExp(`^${Homepage.DATA_TEST_ASSESSMENT_COMPONENT_TITLE}`);
        const activityTitle = component.getByTestId(allAssessmentsSelector).filter({ hasText: ACTIVITY_NAME });
        await expect(activityTitle).toBeVisible();
    });

    test.afterAll(async ({ api, db }) => {
        await api.deleteHomepageComponent(Common.CLASS_ID_9AENG, componentId);

        // Reset AI settings
        for (const [setting, value] of Object.entries(initialSettings)) {
            await db.updateInDatabase("config", { value }, { name: setting });
        }
    });
});

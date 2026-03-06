import { AiFeature } from "@schoolboxLibrary/Admin/OpenAI/Usage/templates/payload";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import AIServices, { AiProvider } from "@schoolboxTest/playwright/support/page/admin/ai/AIServices";
import Common from "@schoolboxTest/playwright/support/page/Common";
import DialogAgentConfigure from "@schoolboxTest/playwright/support/page/dialogAgent/DialogAgentConfigure";
import DialogAgentHomepage from "@schoolboxTest/playwright/support/page/dialogAgent/DialogAgentHomepage";
import DialogAgentModify from "@schoolboxTest/playwright/support/page/dialogAgent/DialogAgentModify";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

const ACTIVITY_NAME_BASE = "Anteresia Stimsoni";
const ACTIVITY_NAME_1 = `${ACTIVITY_NAME_BASE} 1 (unconfigured)`;
const ACTIVITY_NAME_2 = `${ACTIVITY_NAME_BASE} 2 (configured)`;

test.describe("Dialog Agent", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });

    let componentId: number;

    // Keep a copy of the initial AI settings so they can be reset after the tests are done
    const initialSettings: Record<string, string | null> = {
        ai_dialogagent: null,
    };

    test.beforeAll(async ({ db }) => {
        for (const setting of Object.keys(initialSettings)) {
            initialSettings[setting] = (await db.grabFromDatabase("config", "value", { name: setting })) as string;
        }
    });

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("add the component, validate empty state when AI is disabled", async ({ tester, page }) => {
        const homepage = new Homepage(tester, page);

        tester.isGoingTo("Homepage, open tab panel, click components tab");
        await homepage.goto(Common.CLASS_ID_9AENG);

        tester.isGoingTo("Add Discussions (Dialog Agent) component");
        await homepage.addComponent(
            DialogAgentHomepage.HOMEPAGE_ADD_COMPONENT_SECTION,
            DialogAgentHomepage.HOMEPAGE_COMPONENT_NAME,
        );
        const component = homepage.locateComponentByName(DialogAgentHomepage.HOMEPAGE_COMPONENT_NAME);
        componentId = await Homepage.getComponentId(component);

        tester.isGoingTo(
            "expect to see an empty state message indicating that the feature is disabled, and no create button",
        );
        const dialogAgentComponent = new DialogAgentHomepage(page, tester, component);
        await dialogAgentComponent.verifyEmptyStateDisabled();
        await dialogAgentComponent.verifyEmptyStateCreateButtonNotVisible();
    });

    test("enable AI feature", async ({ tester, page }) => {
        tester.isGoingTo("Enable the Dialog Agent feature in AI Services");
        const aiServices = new AIServices(page, tester);
        await aiServices.goto();

        const dialogAgentFeature = aiServices.locateFeature(AiFeature.DIALOG_AGENT);
        await AIServices.selectProviderForFeature(dialogAgentFeature, AiProvider.OPENAI);
        await AIServices.fillOpenAIKeyForFeature(dialogAgentFeature);
        await aiServices.save();
        await aiServices.waitForFeatureToBeEnabled(AiFeature.DIALOG_AGENT);
    });

    test("create an unconfigured discussion activity via the component empty state", async ({ tester, page }) => {
        const homepage = new Homepage(tester, page);
        await homepage.goto(Common.CLASS_ID_9AENG);
        const component = homepage.locateComponentByName(DialogAgentHomepage.HOMEPAGE_COMPONENT_NAME);
        const dialogAgentComponent = new DialogAgentHomepage(page, tester, component);
        await dialogAgentComponent.clickEmptyStateCreateButton();

        tester.isGoingTo("fill out the create form and leave the discussion unconfigured afterwards");
        const assessmentForm = new AssessmentForm(tester, page);
        const dialogAgentModify = new DialogAgentModify(page, tester);
        const dialogAgentConfigure = new DialogAgentConfigure(page, tester);
        await assessmentForm.setTitle(ACTIVITY_NAME_1);
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_HOMEWORK);
        await dialogAgentModify.selectScenario("Self-Reflection");
        await assessmentForm.clickCreate();
        await dialogAgentConfigure.assertIsOnConfigurePage(ACTIVITY_NAME_1);
    });

    test("create a discussion activity via the component, fully configure it", async ({ tester, page }) => {
        const homepage = new Homepage(tester, page);
        await homepage.goto(Common.CLASS_ID_9AENG);
        const component = homepage.locateComponentByName(DialogAgentHomepage.HOMEPAGE_COMPONENT_NAME);
        const dialogAgentComponent = new DialogAgentHomepage(page, tester, component);
        await dialogAgentComponent.clickCreateAction();

        tester.isGoingTo("fill out the create form");
        const assessmentForm = new AssessmentForm(tester, page);
        const dialogAgentModify = new DialogAgentModify(page, tester);
        const dialogAgentConfigure = new DialogAgentConfigure(page, tester);
        await assessmentForm.setTitle(ACTIVITY_NAME_2);
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_HOMEWORK);
        await dialogAgentModify.selectScenario("Self-Reflection");
        await assessmentForm.clickCreate();

        tester.isGoingTo("check sandbox is inactive prior to saving the initial configuration");
        await dialogAgentConfigure.assertIsOnConfigurePage(ACTIVITY_NAME_1);
        await dialogAgentConfigure.assertSandboxDisabled();

        tester.isGoingTo("confirm the AI prefilled each field");
        const fieldTextareas = await dialogAgentConfigure.locateFormFieldTextareas();
        const openingMessageTextarea = dialogAgentConfigure.locateFormOpeningMessageTextarea();

        for (const textarea of [...fieldTextareas, openingMessageTextarea]) {
            const editorContent = await tester.readCKEditorByLocator(textarea);
            expect(editorContent.trim()).not.toBe("");
        }

        tester.isGoingTo("save the initial configuration");
        await dialogAgentConfigure.clickSaveConfiguration();
    });

    test("view both activities in the component", async ({ tester, page }) => {
        const homepage = new Homepage(tester, page);
        await homepage.goto(Common.CLASS_ID_9AENG);
        const component = homepage.locateComponentByName(DialogAgentHomepage.HOMEPAGE_COMPONENT_NAME);

        const allAssessmentsSelector = new RegExp(`^${Homepage.DATA_TEST_ASSESSMENT_COMPONENT_TITLE}`);
        const activityTitle1 = component.getByTestId(allAssessmentsSelector).filter({ hasText: ACTIVITY_NAME_1 });
        await expect(activityTitle1).toBeVisible();

        const activityTitle2 = component.getByTestId(allAssessmentsSelector).filter({ hasText: ACTIVITY_NAME_2 });
        await expect(activityTitle2).toBeVisible();
    });

    test.afterAll(async ({ api, db }) => {
        await api.deleteHomepageComponent(Common.CLASS_ID_9AENG, componentId);

        // Delete any activities created in this test
        const dialogAgentIds = (await db.grabColumnFromDatabase("assessment", "id", {
            "title LIKE": `${ACTIVITY_NAME_BASE}%`,
            deleted_at: null,
        })) as number[];
        await Promise.all(dialogAgentIds.map((id) => api.deleteAssessment(id)));

        // Reset AI settings
        for (const [setting, value] of Object.entries(initialSettings)) {
            await db.updateInDatabase("config", { value }, { name: setting });
        }
    });
});

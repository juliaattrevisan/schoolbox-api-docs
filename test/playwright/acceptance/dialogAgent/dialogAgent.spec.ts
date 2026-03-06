import { AiFeature } from "@schoolboxLibrary/Admin/OpenAI/Usage/templates/payload";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import AIServices, { AiProvider } from "@schoolboxTest/playwright/support/page/admin/ai/AIServices";
import AgentChatFeed from "@schoolboxTest/playwright/support/page/agentChat/AgentChatFeed";
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
        await dialogAgentConfigure.assertIsOnConfigurePage(ACTIVITY_NAME_2);
        await dialogAgentConfigure.assertSandboxDisabled();

        const openingMessageTextarea = dialogAgentConfigure.locateFormOpeningMessageTextarea();
        await test.step("confirm the AI prefilled every field with some content", async () => {
            const fieldTextareas = await dialogAgentConfigure.locateFormFieldTextareas();
            const prefilledTextareas = [...fieldTextareas, openingMessageTextarea];

            const contentPromises = prefilledTextareas.map((textarea) => tester.readCKEditorByLocator(textarea));
            await Promise.all(contentPromises.map((content) => expect(content).resolves.not.toBe("")));
        });

        tester.isGoingTo("save the initial configuration");
        await dialogAgentConfigure.clickSaveConfiguration();

        tester.isGoingTo("confirm the sandbox doesn't show the unsaved changes alert");
        await dialogAgentConfigure.assertSandboxUnsavedAlertNotVisible();

        tester.isGoingTo("confirm there's exactly 1 message in the feed");
        const agentChatFeed = new AgentChatFeed(page, tester);
        await expect(agentChatFeed.locateAllMessages()).toHaveCount(1);

        tester.isGoingTo("confirm the opening message is shown in the sandbox");
        const openingMessage = await tester.readCKEditorByLocator(openingMessageTextarea);
        await agentChatFeed.expectOpeningMessageToHaveText(openingMessage);
    });

    test("configuration sandbox unsaved changes alert", async ({ tester, page }) => {
        const homepage = new Homepage(tester, page);
        await homepage.goto(Common.CLASS_ID_9AENG);
        const component = homepage.locateComponentByName(DialogAgentHomepage.HOMEPAGE_COMPONENT_NAME);
        const dialogAgentConfigure = new DialogAgentConfigure(page, tester);

        tester.isGoingTo("open the configure page for the 2nd activity");
        const activity2 = homepage.locateAssessmentRow(component, "test");
        const activity2Id = await Homepage.getAssessmentId(activity2);

        await homepage.clickAssessmentComponentTOTMenu(activity2Id);
        await homepage.clickAssessmentComponentTOTMenuConfigureAgent(activity2Id);

        tester.isGoingTo("expect no unsaved changes alert on page load");
        await dialogAgentConfigure.assertSandboxUnsavedAlertNotVisible();

        const agentChatFeed = new AgentChatFeed(page, tester);

        await test.step("opening message", async () => {
            tester.isGoingTo("confirm the opening message is shown in the sandbox");
            const openingMessageTextarea = dialogAgentConfigure.locateFormOpeningMessageTextarea();
            const openingMessage = await tester.readCKEditorByLocator(openingMessageTextarea);
            await agentChatFeed.expectOpeningMessageToHaveText(openingMessage);

            tester.isGoingTo("confirm the opening message is shown in the sandbox");

            tester.isGoingTo("add 1 character, expect alert to be visible");
            await tester.focusCKEditorByLocator(openingMessageTextarea);

            await page.keyboard.press("a");
            await dialogAgentConfigure.assertSandboxUnsavedAlertVisible();

            tester.isGoingTo("remove the character (back to original message), expect alert to be hidden");
            await page.keyboard.press("Backspace");
            await dialogAgentConfigure.assertSandboxUnsavedAlertNotVisible();

            tester.isGoingTo("remove another character, expect alert to be visible");
            await page.keyboard.press("Delete"); // note that ckeditor puts the cursor at the START, so this needs to be Delete
            await dialogAgentConfigure.assertSandboxUnsavedAlertVisible();

            tester.isGoingTo(
                "add a different character (same length but different value), expect alert to remain visible",
            );
            const newChar = openingMessage.slice(0, 1) === "a" ? "b" : "a"; // since AI generates dynamic content, make sure it differs...
            await page.keyboard.type(newChar);
            await dialogAgentConfigure.assertSandboxUnsavedAlertVisible();

            tester.isGoingTo("save changes, expect alert to be hidden");
            await dialogAgentConfigure.clickSaveConfiguration();
            await dialogAgentConfigure.assertSandboxUnsavedAlertNotVisible();

            tester.isGoingTo("confirm the new opening message is shown in the sandbox");
            const updatedOpeningMessage = `${newChar}${openingMessage.slice(1)}`;
            await agentChatFeed.expectOpeningMessageToHaveText(updatedOpeningMessage);
        });
    });

    test("view both activities in the component", async ({ tester, page }) => {
        const homepage = new Homepage(tester, page);
        await homepage.goto(Common.CLASS_ID_9AENG);
        const component = homepage.locateComponentByName(DialogAgentHomepage.HOMEPAGE_COMPONENT_NAME);

        const activity1 = homepage.locateAssessmentRow(component, ACTIVITY_NAME_1);
        const activity2 = homepage.locateAssessmentRow(component, ACTIVITY_NAME_2);

        await expect(activity1).toBeVisible();
        await expect(activity2).toBeVisible();
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

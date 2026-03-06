import { AiFeature } from "@schoolboxLibrary/Admin/OpenAI/Usage/templates/payload";
import { AgentChatMessageType } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import { DialogAgentHelpfulness } from "@schoolboxLibrary/Learning/Assessment/templates/dialogAgent/vue/configure/dacTypes";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import AIServices, { AiProvider } from "@schoolboxTest/playwright/support/page/admin/ai/AIServices";
import AgentChatFeed from "@schoolboxTest/playwright/support/page/agentChat/AgentChatFeed";
import AgentChatPrompt from "@schoolboxTest/playwright/support/page/agentChat/AgentChatPrompt";
import Common from "@schoolboxTest/playwright/support/page/Common";
import DialogAgentConfigure from "@schoolboxTest/playwright/support/page/dialogAgent/DialogAgentConfigure";
import DialogAgentHomepage from "@schoolboxTest/playwright/support/page/dialogAgent/DialogAgentHomepage";
import DialogAgentModify from "@schoolboxTest/playwright/support/page/dialogAgent/DialogAgentModify";
import DialogAgentResponse from "@schoolboxTest/playwright/support/page/dialogAgent/DialogAgentResponse";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import AssessmentPage from "@schoolboxTest/playwright/support/page/dueWork/AssessmentPage";
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
        await assessmentForm.setDescription("A science lesson about the Stimson's python");
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_HOMEWORK);
        await dialogAgentModify.selectScenario("Socratic Tutor");
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

    test("unsaved configuration changes alert", async ({ tester, page }) => {
        const homepage = new Homepage(tester, page);
        await homepage.goto(Common.CLASS_ID_9AENG);
        const component = homepage.locateComponentByName(DialogAgentHomepage.HOMEPAGE_COMPONENT_NAME);
        const dialogAgentConfigure = new DialogAgentConfigure(page, tester);

        tester.isGoingTo("open the configure page for the 2nd activity");
        const activity2 = homepage.locateAssessmentRow(component, ACTIVITY_NAME_2);
        const activity2Id = await Homepage.getAssessmentId(activity2);

        await homepage.clickAssessmentComponentTOTMenu(activity2Id);
        await homepage.clickAssessmentComponentTOTMenuConfigureAgent(activity2Id);

        tester.isGoingTo("expect no unsaved changes alert on page load");
        await dialogAgentConfigure.assertSandboxUnsavedAlertNotVisible();

        await test.step("opening message", async () => {
            tester.isGoingTo("confirm the opening message is shown in the sandbox");
            const openingMessageTextarea = dialogAgentConfigure.locateFormOpeningMessageTextarea();
            const openingMessage = await tester.readCKEditorByLocator(openingMessageTextarea);

            const agentChatFeed = new AgentChatFeed(page, tester);
            await agentChatFeed.expectOpeningMessageToHaveText(openingMessage);

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

        await test.step("helpfulness setting", async () => {
            tester.isGoingTo("change the helpfulness setting, expect alert to be visible");
            await dialogAgentConfigure.setHelpfulness(DialogAgentHelpfulness.DIRECTED);
            await dialogAgentConfigure.assertSandboxUnsavedAlertVisible();

            tester.isGoingTo("change the helpfulness setting back to the original value, expect alert to be hidden");
            await dialogAgentConfigure.setHelpfulness(DialogAgentHelpfulness.GUIDED);
            await dialogAgentConfigure.assertSandboxUnsavedAlertNotVisible();

            tester.isGoingTo("change the helpfulness setting again, expect alert to be visible");
            await dialogAgentConfigure.setHelpfulness(DialogAgentHelpfulness.EXPLORATORY);
            await dialogAgentConfigure.assertSandboxUnsavedAlertVisible();

            tester.isGoingTo("save changes, expect alert to be hidden");
            await dialogAgentConfigure.clickSaveConfiguration();
            await dialogAgentConfigure.assertSandboxUnsavedAlertNotVisible();
        });

        await test.step("language setting", async () => {
            tester.isGoingTo("change the language setting, expect alert to be visible");
            await dialogAgentConfigure.setLanguage("French");
            await dialogAgentConfigure.assertSandboxUnsavedAlertVisible();

            tester.isGoingTo("change the language setting back to the original value, expect alert to be hidden");
            await dialogAgentConfigure.setLanguage("English (AU)");
            await dialogAgentConfigure.assertSandboxUnsavedAlertNotVisible();

            tester.isGoingTo("change the language setting again, expect alert to be visible");
            await dialogAgentConfigure.setLanguage("English (US)");
            await dialogAgentConfigure.assertSandboxUnsavedAlertVisible();

            tester.isGoingTo("save changes, expect alert to be hidden");
            await dialogAgentConfigure.clickSaveConfiguration();
            await dialogAgentConfigure.assertSandboxUnsavedAlertNotVisible();
        });
    });

    test("as a student, complete an attempt", async ({ tester, page, context }) => {
        await tester.impersonate(Common.USER_JANAREED);

        const homepage = new Homepage(tester, page);
        await homepage.goto(Common.CLASS_ID_9AENG);

        const component = homepage.locateComponentByName(DialogAgentHomepage.HOMEPAGE_COMPONENT_NAME);

        tester.isGoingTo("start an attempt for activity 2");
        const activity2 = homepage.locateAssessmentRow(component, ACTIVITY_NAME_2);
        const activity2Id = await Homepage.getAssessmentId(activity2);
        await homepage.clickAssessmentComponentTitle(activity2Id);

        const discussionHistory = new AssessmentPage(tester, page);
        await discussionHistory.clickCreateSubmission();

        const dialogAgentResponse = new DialogAgentResponse(page, tester);
        await dialogAgentResponse.assertIsOnResponsePage(ACTIVITY_NAME_2);

        tester.isGoingTo("expect to only see the opening message in the feed");
        const agentChatFeed = new AgentChatFeed(page, tester);
        const agentChatPrompt = new AgentChatPrompt(page, tester);

        const allRequestMessages = agentChatFeed.locateMessagesWithType(AgentChatMessageType.REQUEST);
        const allResponseMessages = agentChatFeed.locateMessagesWithType(AgentChatMessageType.RESPONSE);

        await expect(allRequestMessages).toHaveCount(0);
        await expect(allResponseMessages).toHaveCount(1);

        await test.step("send a message in the chat", async () => {
            const prompt1 = "What is the python's habitat?";
            await agentChatPrompt.typePrompt(prompt1);
            await agentChatPrompt.send();

            tester.isGoingTo("expect to see the student's prompt in the feed, and a receive response from the AI");
            await AgentChatFeed.expectMessageToHaveText(allRequestMessages.last(), prompt1);
            await AgentChatFeed.expectMessageIsLoading(allResponseMessages.last());
            await AgentChatFeed.waitForMessageLoaded(allResponseMessages.last());

            await expect(allRequestMessages).toHaveCount(1);
            await expect(allResponseMessages).toHaveCount(2);
        });

        tester.isGoingTo("emulate network being offline");
        await context.setOffline(true);

        const prompt2 = "What do they eat?";
        await test.step("simulate a failed response, expect to see an error", async () => {
            await agentChatPrompt.typePrompt(prompt2);
            await agentChatPrompt.send();

            await expect(allRequestMessages).toHaveCount(2);
            await expect(allResponseMessages).toHaveCount(3);

            await AgentChatFeed.expectMessageToHaveText(
                allResponseMessages.last(),
                "There was a problem retrieving the response. Failed to fetch",
            );
        });

        await test.step("retry the failed message, (with network off), expect a 2nd error message", async () => {
            await AgentChatFeed.clickRetry(allResponseMessages.last());

            await expect(allRequestMessages).toHaveCount(3);
            await expect(allResponseMessages).toHaveCount(4);

            await AgentChatFeed.expectMessageToHaveText(
                allResponseMessages.last(),
                "There was a problem retrieving the response. Failed to fetch",
            );
        });

        tester.isGoingTo("restore network connection");
        await context.setOffline(false);

        await test.step("retry the failed message again, expect a successful response", async () => {
            await AgentChatFeed.clickRetry(allResponseMessages.last());

            await AgentChatFeed.expectMessageToHaveText(allRequestMessages.last(), prompt2);
            await AgentChatFeed.expectMessageIsLoading(allResponseMessages.last());
            await AgentChatFeed.waitForMessageLoaded(allResponseMessages.last());

            await expect(allRequestMessages).toHaveCount(4);
            await expect(allResponseMessages).toHaveCount(5);
        });

        await dialogAgentResponse.clickSubmit();
        await dialogAgentResponse.clickConfirmSubmission();

        tester.isGoingTo("expect to see the attempt in submission history");
        await expect(discussionHistory.locateAllInteractions()).toHaveCount(1);
    });

    test("as a staff member, see both activities in the component", async ({ tester, page }) => {
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

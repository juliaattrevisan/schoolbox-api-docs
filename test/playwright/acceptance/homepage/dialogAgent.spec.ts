import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Common from "@schoolboxTest/playwright/support/page/Common";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

const EMPTY_STATE_MESSAGE_AI_DISABLED = "The Discussion feature is currently disabled";
const COMPONENT_NAME = "Discussions"; // Terminology for Dialog Agent is "Discussions"

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

        tester.isGoingTo("expect to see an empty state message indicating that the feature is disabled");
        await expect(component).toContainText(EMPTY_STATE_MESSAGE_AI_DISABLED);
    });

    test.afterAll(async ({ api, db }) => {
        await api.deleteHomepageComponent(Common.CLASS_ID_9AENG, componentId);

        // Reset AI settings
        for (const [setting, value] of Object.entries(initialSettings)) {
            await db.updateInDatabase("config", { value }, { name: setting });
        }
    });
});

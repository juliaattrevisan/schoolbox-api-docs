import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class DialogAgentModify {
    static readonly DATA_TEST_SCENARIO_SELECT = "dialog-agent-scenario-select"; // append -{scenarioId}
    static readonly DATA_TEST_SCENARIO_SELECT_LABEL = "dialog-agent-scenario-select-label";

    constructor(
        private page: Page,
        private tester: AcceptanceTester,
    ) {}

    /**
     * Select a scenario
     * @param scenarioTitle - the title of the scenario to select (e.g. "Self-Reflection")
     */
    async selectScenario(scenarioTitle: string): Promise<void> {
        const anyScenarioSelectRegex = new RegExp(`^${DialogAgentModify.DATA_TEST_SCENARIO_SELECT}`);
        const allScenarioLabels = this.page
            .getByTestId(anyScenarioSelectRegex)
            .getByTestId(DialogAgentModify.DATA_TEST_SCENARIO_SELECT_LABEL);

        const scenarioLabel = allScenarioLabels.filter({ hasText: scenarioTitle });
        await scenarioLabel.click();
    }
}

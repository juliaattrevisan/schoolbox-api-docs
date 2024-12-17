import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class ManageCurriculum {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly URL = "/curriculum";

    static readonly TOAST = ".toast";

    static readonly NODES = "[data-test-node]:not([data-test-view-node])";

    static readonly DATATEST_NODE_NAME = "view-node-name";

    static readonly DATATEST_MC_VIEW_NODES_LIST = "mc-view-nodes-list";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * Navigate to the curriculum page
     */
    async goto(): Promise<void> {
        await this.page.goto(ManageCurriculum.URL);
    }

    /**
     * Locate a given node's row by its name
     * @param name - The name of the node
     * @returns The locator of the node
     */
    async locateNode(name: string): Promise<Locator> {
        // The name container elements that match the text
        const matchingNameContainer = this.page
            .getByTestId(ManageCurriculum.DATATEST_NODE_NAME)
            .filter({ hasText: name });

        // Filter the full node rows by the matching name container
        const nodeRow = this.page.locator(ManageCurriculum.NODES).filter({ has: matchingNameContainer });
        await expect(nodeRow).toHaveCount(1);

        return nodeRow;
    }

    /**
     * select the node checkbox to enable/disable or delete
     * @param name - The name of the node
     */
    async checkNode(name: string): Promise<void> {
        const node = await this.locateNode(name);
        const checkboxId = await node.locator("input[type=checkbox]").getAttribute("id");
        const checkboxLabel = node.locator(`label[for=${checkboxId}]`);
        await checkboxLabel.click();
    }

    /**
     * select the node to navigate next to
     * @param value - The locator of the node
     */
    async clickNode(value: string): Promise<void> {
        const node = await this.locateNode(value);
        await node.getByTestId(ManageCurriculum.DATATEST_NODE_NAME).click();
    }
}

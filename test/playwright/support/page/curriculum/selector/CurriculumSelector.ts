/**
 * Utility class for selecting curriculum items
 * linked to: library/Schoolbox/Curriculum/templates/vue/curriculumSelector/curriculum-selector.vue.vue
 */

import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class CurriculumSelector {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly DATA_TEST_BROWSE_BUTTON = "cs-search-input-browse-button";

    static readonly DATA_TEST_MODAL_TABS = "cs-modal-tabs";

    static readonly DATA_TEST_MODAL_CURRENT_TAB_BUTTON = "cs-modal-current-tab-button";

    static readonly DATA_TEST_MODAL_ALL_TAB_BUTTON = "cs-modal-all-tab-button";

    static readonly DATA_TEST_MODAL_NODE_NAME = "cs-modal-all-node-name-";

    static readonly DATA_TEST_MODAL_NODE_CHECKBOX_LABEL = "cs-modal-node-checkbox-label-";

    static readonly DATA_TEST_MODAL_CURRENT_NODES_CONTAINER = "cs-modal-current-nodes-container";

    static readonly DATA_TEST_MODAL_CURRENT_NODE_CHECKBOX_LABEL = "cs-modal-current-node-checkbox-label-";

    static readonly DATA_TEST_MODAL_SAVE_BUTTON = "cs-modal-footer-save-button";

    static readonly DATA_TEST_SELECTED_NODES_CONTAINER = "cs-selected-nodes-container";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Clicks the browse button to open the modal
     * @param $context - The context to use when there are multiple curriculum selectors on the page
     */
    async clickBrowse($context?: Locator): Promise<void> {
        const context = $context || this.page;
        await context.getByTestId(CurriculumSelector.DATA_TEST_BROWSE_BUTTON).click();
    }

    /**
     * Return the modal tabs
     * @param $context - The context to use when there are multiple curriculum selectors on the page
     * @returns - The modal tabs
     */
    getModalTabs($context?: Locator): Locator {
        const context = $context || this.page;
        return context.getByTestId(CurriculumSelector.DATA_TEST_MODAL_TABS);
    }

    /**
     * Clicks the Current tab
     * @param $context - The context to use when there are multiple curriculum selectors on the page
     */
    async clickCurrentTab($context?: Locator): Promise<void> {
        const context = $context || this.page;
        await context.getByTestId(CurriculumSelector.DATA_TEST_MODAL_CURRENT_TAB_BUTTON).click();
    }

    /**
     * Clicks the All tab
     * @param $context - The context to use when there are multiple curriculum selectors on the page
     */
    async clickAllTab($context?: Locator): Promise<void> {
        const context = $context || this.page;
        await context.getByTestId(CurriculumSelector.DATA_TEST_MODAL_ALL_TAB_BUTTON).click();
    }

    /**
     * Browse the node tree
     * @param ids - The node ids to select
     * @param $context - The context to use when there are multiple curriculum selectors on the page
     */
    async browseToNode(ids: number[], $context?: Locator): Promise<void> {
        const context = $context || this.page;
        for (const id of ids) {
            await context.getByTestId(`${CurriculumSelector.DATA_TEST_MODAL_NODE_NAME}${id.toString()}`).click();
        }
    }

    /**
     * Select the nodes in the modal
     * @param ids - The node ids to select
     * @param $context - The context to use when there are multiple curriculum selectors on the page
     */
    async selectNodes(ids: number[], $context?: Locator): Promise<void> {
        const context = $context || this.page;
        for (const id of ids) {
            await context
                .getByTestId(`${CurriculumSelector.DATA_TEST_MODAL_NODE_CHECKBOX_LABEL}${id.toString()}`)
                .click();
        }
    }

    /**
     * Expect the unit modal container to contain the text
     * @param text - The text to not expect
     * @param $context - The context to use when there are multiple curriculum selectors on the page
     */
    async expectUnitModalToContainText(text: string, $context?: Locator): Promise<void> {
        const context = $context || this.page;
        await expect(context.getByTestId(CurriculumSelector.DATA_TEST_MODAL_CURRENT_NODES_CONTAINER)).toContainText(
            text,
        );
    }

    /**
     * Select the nodes in the modal within the 'Current' tab
     * @param ids - The node ids to select
     * @param $context - The context to use when there are multiple curriculum selectors on the page
     */
    async selectUnitNodes(ids: number[], $context?: Locator): Promise<void> {
        const context = $context || this.page;
        for (const id of ids) {
            await context
                .getByTestId(`${CurriculumSelector.DATA_TEST_MODAL_CURRENT_NODE_CHECKBOX_LABEL}${id.toString()}`)
                .click();
        }
    }

    /**
     * Select Save, closing the modal
     * @param $context - The context to use when there are multiple curriculum selectors on the page
     */
    async saveModal($context?: Locator): Promise<void> {
        const context = $context || this.page;
        await context.getByTestId(CurriculumSelector.DATA_TEST_MODAL_SAVE_BUTTON).click();
        await this.tester.common.waitForModalToBeClosed();
    }

    /**
     * Expect the selected nodes to contain the text
     * @param text - The text to expect
     * @param $context - The context to use when there are multiple curriculum selectors on the page
     */
    async expectSelectedToContain(text: string, $context?: Locator): Promise<void> {
        const context = $context || this.page;
        await expect(context.getByTestId(CurriculumSelector.DATA_TEST_SELECTED_NODES_CONTAINER)).toContainText(text);
    }

    /**
     * Expect the selected nodes to not contain the text
     * @param text - The text to not expect
     * @param $context - The context to use when there are multiple curriculum selectors on the page
     */
    async expectSelectedNotToContain(text: string, $context?: Locator): Promise<void> {
        const context = $context || this.page;
        await expect(context.getByTestId(CurriculumSelector.DATA_TEST_SELECTED_NODES_CONTAINER)).not.toContainText(
            text,
        );
    }

    /**
     * Expect the selected nodes to be empty (so not be visible at all)
     * @param $context - The context to use when there are multiple curriculum selectors on the page
     */
    async expectSelectedToBeEmpty($context?: Locator): Promise<void> {
        const context = $context || this.page;
        await expect(context.getByTestId(CurriculumSelector.DATA_TEST_SELECTED_NODES_CONTAINER)).not.toBeVisible();
    }
}

/**
 * Utility class for selecting curriculum items
 * linked to: library/Schoolbox/Curriculum/templates/vue/curriculumSelector/curriculum-selector.vue.vue
 */

import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";

export default class CurriculumSelector {
    private page: Page;

    static readonly DATA_TEST_BROWSE_BUTTON = "cs-search-input-browse-button";

    static readonly DATA_TEST_MODAL_TABS = "cs-modal-tabs";

    static readonly DATA_TEST_MODAL_NODE_NAME = "cs-modal-all-node-name-";

    static readonly DATA_TEST_MODAL_NODE_CHECKBOX_LABEL = "cs-modal-node-checkbox-label-";

    static readonly DATA_TEST_MODAL_UNIT_NODES_CONTAINER = "cs-modal-unit-nodes-container";

    static readonly DATA_TEST_MODAL_UNIT_NODE_CHECKBOX_LABEL = "cs-modal-unit-node-checkbox-label-";

    static readonly DATA_TEST_MODAL_NODE_VIEW_LABEL = "cs-modal-view-node-checkbox-label-";

    static readonly DATA_TEST_MODAL_SAVE_BUTTON = "cs-modal-footer-save-button";

    static readonly DATA_TEST_SELECTED_NODES_CONTAINER = "cs-selected-nodes-container";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Clicks the browse button to open the modal
     */
    async clickBrowse(): Promise<void> {
        await this.page.getByTestId(CurriculumSelector.DATA_TEST_BROWSE_BUTTON).click();
    }

    /**
     * Return the modal tabs
     * @returns - The modal tabs
     */
    getModalTabs(): Locator {
        return this.page.locator(CurriculumSelector.DATA_TEST_MODAL_TABS);
    }

    /**
     * Browse the node tree
     * @param ids - The node ids to select
     */
    async browseToNode(ids: number[]): Promise<void> {
        // eslint-disable-next-line no-restricted-syntax -- need to loop through each sequentially, rather than in parallel with Promise.all
        for (const id of ids) {
            // eslint-disable-next-line no-await-in-loop -- need to click each item sequentially
            await this.page.getByTestId(`${CurriculumSelector.DATA_TEST_MODAL_NODE_NAME}${id.toString()}`).click();
        }
    }

    /**
     * Select the nodes in the modal
     * @param ids - The node ids to select
     */
    async selectNodes(ids: number[]): Promise<void> {
        // eslint-disable-next-line no-restricted-syntax -- need to loop through each sequentially, rather than in parallel with Promise.all
        for (const id of ids) {
            // eslint-disable-next-line no-await-in-loop -- need to click each item sequentially
            await this.page
                .getByTestId(`${CurriculumSelector.DATA_TEST_MODAL_NODE_CHECKBOX_LABEL}${id.toString()}`)
                .click();
        }
    }

    /**
     * Expect the unit modal container to contain the text
     * @param text - The text to not expect
     */
    async expectUnitModalToContainText(text: string): Promise<void> {
        await expect(this.page.getByTestId(CurriculumSelector.DATA_TEST_MODAL_UNIT_NODES_CONTAINER)).toContainText(
            text,
        );
    }

    /**
     * Select the nodes in the modal within the 'Current' tab
     * @param ids - The node ids to select
     */
    async selectUnitNodes(ids: number[]): Promise<void> {
        // eslint-disable-next-line no-restricted-syntax -- need to loop through each sequentially, rather than in parallel with Promise.all
        for (const id of ids) {
            // eslint-disable-next-line no-await-in-loop -- need to click each item sequentially
            await this.page
                .getByTestId(`${CurriculumSelector.DATA_TEST_MODAL_UNIT_NODE_CHECKBOX_LABEL}${id.toString()}`)
                .click();
        }
    }

    /**
     * Tick a node in curriculum browser modal
     * @param id - The node ids to navigate to
     */
    async checkNode(id: number): Promise<void> {
        await this.page.getByTestId(`${CurriculumSelector.DATA_TEST_MODAL_NODE_CHECKBOX_LABEL}${id}`).click();
    }

    /**
     * Select Save, closing the modal
     */
    async saveModal(): Promise<void> {
        await this.page.getByTestId(CurriculumSelector.DATA_TEST_MODAL_SAVE_BUTTON).click();
    }

    /**
     * Expect the selected nodes to contain the text
     * @param text - The text to expect
     */
    async expectSelectedToContain(text: string): Promise<void> {
        await expect(this.page.getByTestId(CurriculumSelector.DATA_TEST_SELECTED_NODES_CONTAINER)).toContainText(text);
    }

    /**
     * Expect the selected nodes to not contain the text
     * @param text - The text to not expect
     */
    async expectSelectedNotToContain(text: string): Promise<void> {
        await expect(this.page.getByTestId(CurriculumSelector.DATA_TEST_SELECTED_NODES_CONTAINER)).not.toContainText(
            text,
        );
    }

    /**
     * Expect the selected nodes to be empty (so not be visible at all)
     */
    async expectSelectedToBeEmpty(): Promise<void> {
        await expect(this.page.getByTestId(CurriculumSelector.DATA_TEST_SELECTED_NODES_CONTAINER)).not.toBeVisible();
    }
}

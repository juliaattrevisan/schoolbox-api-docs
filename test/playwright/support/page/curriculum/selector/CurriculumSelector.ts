/**
 * Utility class for selecting curriculum items
 * linked to: library/Schoolbox/Curriculum/templates/vue/curriculumSelector/curriculum-selector.vue.vue
 */

import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";

export default class CurriculumSelector {
    private page: Page;

    static readonly DATA_TEST_BROWSE_BUTTON = "cs-search-input-browse-button";

    static readonly DATA_TEST_MODAL_NODE_NAME = "cs-modal-all-node-name-";

    static readonly DATA_TEST_MODAL_NODE_CHECKBOX_LABEL = "cs-modal-node-checkbox-label-";

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
}

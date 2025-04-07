/**
 * Utility class for selecting curriculum items
 * linked to: library/Schoolbox/Curriculum/templates/vue/curriculumSelector/curriculum-selector.vue.vue
 */

import type { Page } from "@playwright/test";

export default class CurriculumSelector {
    private page: Page;

    static readonly DATA_TEST_MODAL_NODE_CHECKBOX_LABEL = "cs-modal-node-checkbox-label-";

    static readonly DATA_TEST_MODAL_SAVE_BUTTON = "cs-modal-footer-save-button";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Browse the curriculum browser modal
     * @param ids - The node ids to navigate to
     */
    async browseTree(ids: number[]): Promise<void> {
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
}

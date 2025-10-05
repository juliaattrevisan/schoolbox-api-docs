/**
 * Utility class for accessing the search select modal component
 * linked to: vue/components/searchSelect/modal/search-select-modal.vue
 */

import type { Page } from "@playwright/test";

export default class SearchSelectModal {
    private page: Page;

    static readonly DATA_TEST_RESULTS_ITEM_LABEL = "ss-results-item-label-";

    static readonly DATA_TEST_SAVE_BUTTON = "ssm-footer-save-button";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * To select result items by id
     * @param ids - The ids of the result items
     */
    async selectResults(ids: number[]): Promise<void> {
        for (const id of ids) {
            await this.page.getByTestId(`${SearchSelectModal.DATA_TEST_RESULTS_ITEM_LABEL}${id.toString()}`).click();
        }
    }

    /**
     * Select Save, closing the modal
     */
    async save(): Promise<void> {
        await this.page.getByTestId(SearchSelectModal.DATA_TEST_SAVE_BUTTON).click();
    }
}

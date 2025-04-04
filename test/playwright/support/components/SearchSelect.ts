/**
 * Utility class for accessing the search select component
 * linked to: vue/components/searchSelect/search-select.vue
 */

import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";

export default class SearchSelect {
    private page: Page;

    static readonly DATA_TEST_SELECTED = "ss-selected";

    static readonly DATA_TEST_BUTTON = "search-select-button-";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Check that a selected item is the one expected
     * @param name - The name of the selected SearchSelectItem
     */
    async expectItemSelected(name: string): Promise<void> {
        await expect(this.page.getByTestId(SearchSelect.DATA_TEST_SELECTED)).toContainText(name);
    }

    /**
     * Select the button that can be next to the input
     * @param id - The id of the button
     */
    async selectButton(id: string): Promise<void> {
        await this.page.getByTestId(`${SearchSelect.DATA_TEST_BUTTON}${id}`).click();
    }
}

import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class SearchNodes {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly DATATEST_SEARCH_FIELD = "search-input";

    static readonly DATATEST_SEARCH_RESULTS = "[data-test-node]";

    static readonly DATATEST_TOGGLE_SHOW_DISABLED_NODES = "show-only-enabled";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * Fill the search field with a value
     * @param value - The value to search for
     */
    async fillSearch(value: string): Promise<void> {
        await this.page.getByTestId(SearchNodes.DATATEST_SEARCH_FIELD).fill(value);
    }

    /**
     * Check the number of search results
     * @param count - The expected number of search results
     */
    async seeResultsMatch(count: number): Promise<void> {
        await expect(this.page.locator(SearchNodes.DATATEST_SEARCH_RESULTS)).toHaveCount(count);
    }

    /**
     * hide disabled nodes
     */
    async uncheckShowDisabledNodes(): Promise<void> {
        await this.page.getByTestId(SearchNodes.DATATEST_TOGGLE_SHOW_DISABLED_NODES).uncheck();
    }
}

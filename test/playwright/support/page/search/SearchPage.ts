import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";

export default class SearchPage {
    private page: Page;

    static readonly URL = "/search";

    // Constants for data-test selectors
    static readonly RESULTS_RESULT = "sp-results-result";

    static readonly RESULTS_ERROR = "sp-results-error";

    static readonly RESULTS_NONE = "sp-results-none";

    static readonly RESULTS_LOAD_MORE_BUTTON = "sp-results-load-more-button";

    static readonly RESULTS_LOAD_MORE_ALL_LOADED = "sp-results-load-more-all-loaded";

    static readonly RESULTS_SUB_RESULTS_RESULT = "sp-results-sub-results-result";

    static readonly RESULTS_SUB_RESULTS_LOAD_MORE = "sp-results-sub-results-load-more";

    static readonly SEARCH_FIELD = "sp-search-field";

    static readonly SUBMIT_BUTTON = "sp-submit-button";

    static readonly FILTER_CLEAR_BUTTON = "sp-filter-clear-button";

    static readonly FILTER_APPLY_BUTTON = "sp-filter-apply-button";

    static readonly FILTER_DATE_RANGE_END = "sp-filter-date-range-end";

    static readonly FILTER_TYPE_LABEL = "sp-filter-type-label"; // Append "-[type]" like "-news", "-blog" etc.

    static readonly CREATOR_SEARCH_SELECT_INPUT = "search-select-input";

    static readonly CREATOR_SEARCH_SELECT_RESULT = "search-select-result"; // Append "-id"

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigate to the search page
     * @param queryParams - The query parameters to append to the URL [optional]
     */
    async goto(queryParams?: string): Promise<void> {
        await this.page.goto(SearchPage.URL + (queryParams || ""));
    }

    /**
     * Click the search submit button
     */
    async clickSearchButton(): Promise<void> {
        await this.page.getByTestId(SearchPage.SUBMIT_BUTTON).click();
    }

    /**
     * Verify the contents of the search error message
     */
    async verifySearchError(): Promise<void> {
        await expect(this.page.getByTestId(SearchPage.RESULTS_ERROR)).toHaveText(
            "Search keyword must be at least 2 characters",
        );
    }

    /**
     * Verify the number of results displayed
     * @param expectedCount - The expected number of results
     */
    async verifyResultsCount(expectedCount: number): Promise<void> {
        // match testId's that start with SearchPage.RESULTS_RESULT
        const testId = new RegExp(`^${SearchPage.RESULTS_RESULT}`);
        await expect(this.page.getByTestId(testId)).toHaveCount(expectedCount);
    }

    /**
     * Verify that all results are loaded
     */
    async verifyAllResultsLoaded(): Promise<void> {
        await expect(this.page.getByTestId(SearchPage.RESULTS_LOAD_MORE_ALL_LOADED)).toBeVisible();
    }

    /**
     * Verify that no results are displayed
     */
    async verifyNoResults(): Promise<void> {
        await expect(this.page.getByTestId(SearchPage.RESULTS_NONE)).toBeVisible();
    }

    /**
     * Input a keyword in the search field
     * @param keyword - The keyword to search for
     */
    async inputKeyword(keyword: string): Promise<void> {
        await this.page.getByTestId(SearchPage.SEARCH_FIELD).fill(keyword);
    }

    /**
     * Verify the contents of the search field
     * @param keyword - The keyword to verify
     */
    async verifyKeyword(keyword: string): Promise<void> {
        await expect(this.page.getByTestId(SearchPage.SEARCH_FIELD)).toHaveValue(keyword);
    }

    /**
     * Clear all filters
     */
    async clearFilters(): Promise<void> {
        await this.page.getByTestId(SearchPage.FILTER_CLEAR_BUTTON).click();
    }

    /**
     * Click the apply filters button
     */
    async clickApplyFilters(): Promise<void> {
        await this.page.getByTestId(SearchPage.FILTER_APPLY_BUTTON).click();
    }

    /**
     * Click a filter type (e.g., news)
     * @param filterType - The type of filter to click
     */
    async clickFilterType(filterType: string): Promise<void> {
        await this.page.getByTestId(`${SearchPage.FILTER_TYPE_LABEL}-${filterType}`).click();
    }

    /**
     * Click the load more results button
     */
    async clickLoadMoreResults(): Promise<void> {
        await this.page.getByTestId(SearchPage.RESULTS_LOAD_MORE_BUTTON).click();
    }

    /**
     * Click the load more sub results button
     * @param resultId - The ID of the result to load more sub results for
     */
    async clickLoadMoreSubResults(resultId: number): Promise<void> {
        await this.page.getByTestId(`${SearchPage.RESULTS_SUB_RESULTS_LOAD_MORE}-${resultId}`).click();
    }

    /**
     * Verify the number of sub results displayed
     * @param resultId - The ID of the result to verify sub results for
     * @param expectedCount - The expected number of sub results
     */
    async verifySubResultsCount(resultId: number, expectedCount: number): Promise<void> {
        // match testId's that start with SearchPage.RESULTS_SUB_RESULTS_RESULT-resultId
        const testId = new RegExp(`^${SearchPage.RESULTS_SUB_RESULTS_RESULT}-${resultId}`);
        await expect(this.page.getByTestId(testId)).toHaveCount(expectedCount);
    }

    /**
     * Input a date in the end date field
     * @param date - The date to input
     */
    async inputEndDate(date: string): Promise<void> {
        await this.page.getByTestId(SearchPage.FILTER_DATE_RANGE_END).fill(date);
    }

    /**
     * Input a keyword in the creator search field
     * @param keyword - The keyword to input
     */
    async inputCreatorKeyword(keyword: string): Promise<void> {
        await this.page.getByTestId(SearchPage.CREATOR_SEARCH_SELECT_INPUT).fill(keyword);
    }

    /**
     * Select a creator result by ID
     * @param resultId - The ID of the result to select
     */
    async selectCreatorResult(resultId: number): Promise<void> {
        await this.page.getByTestId(`${SearchPage.CREATOR_SEARCH_SELECT_RESULT}-${resultId}`).click();
    }
}

import { expect, type Locator, type Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class UserBulkModify {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/adminv2/user/bulk/modify";

    static readonly DATA_TEST_CAMPUS_FILTER = "bulk-user-modify-campus-filter";

    static readonly DATA_TEST_ROLE_FILTER = "bulk-user-modify-role-filter";

    static readonly DATA_TEST_YEAR_FILTER = "bulk-user-modify-year-filter";

    static readonly DATA_TEST_TOKEN_FILTER = "bulk-user-modify-token-filter";

    static readonly DATA_TEST_EPORTFOLIO_FILTER = "bulk-user-modify-eportfolio-filter";

    static readonly DATA_TEST_DELETE_BUTTON = "user-bulk-modify-delete-button";

    static readonly COLUMN_FIRST_NAME = 1;

    static readonly COLUMN_SURNAME = 3;

    static readonly COLUMN_EXTERNAL_ID = 4;

    static readonly COLUMN_CAMPUSES = 5;

    static readonly COLUMN_ROLE = 6;

    static readonly COLUMN_YEAR_LEVEL = 7;

    static readonly COLUMN_EPORTFOLIO = 9;

    static readonly SELECTOR_INPUT_SEARCH = 'input[placeholder="Search for users..."]';

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigate to the user bulk modify page.
     */
    async goTo(): Promise<void> {
        this.tester.isGoingTo("Navigate to the user bulk modify page");
        await this.page.goto(UserBulkModify.URL);
        await this.expectLoaded();
    }

    /**
     * Confirm the bulk modify Vue table has loaded with users.
     */
    async expectLoaded(): Promise<void> {
        await expect(this.page.getByRole("heading", { name: "User Management › Bulk Modify" })).toBeVisible();
        await expect(this.getRows()).toHaveCount(25);
    }

    /**
     * Search for users in the Vue table.
     * @param searchText - Text to search for.
     */
    async searchUsers(searchText: string): Promise<void> {
        this.tester.isGoingTo(`Search for users using "${searchText}"`);
        const searchInput = this.page.locator(UserBulkModify.SELECTOR_INPUT_SEARCH);
        await searchInput.click();
        await searchInput.clear();
        await searchInput.fill(searchText);
    }

    /**
     * Clear the users search input.
     */
    async clearSearch(): Promise<void> {
        this.tester.isGoingTo("Clear the users search input");
        const searchInput = this.page.locator(UserBulkModify.SELECTOR_INPUT_SEARCH);
        await searchInput.click();
        await searchInput.clear();
    }

    /**
     * Select the campus filter.
     * @param campus - Campus filter value.
     */
    async filterByCampus(campus: string): Promise<void> {
        this.tester.isGoingTo(`Filter users by campus "${campus || "Any"}"`);
        await this.page.getByTestId(UserBulkModify.DATA_TEST_CAMPUS_FILTER).selectOption(campus);
    }

    /**
     * Select the role filter.
     * @param role - Role filter value.
     */
    async filterByRole(role: string): Promise<void> {
        this.tester.isGoingTo(`Filter users by role "${role || "Any"}"`);
        await this.page.getByTestId(UserBulkModify.DATA_TEST_ROLE_FILTER).selectOption(role);
    }

    /**
     * Select the year level filter.
     * @param yearLevel - Year level filter value.
     */
    async filterByYearLevel(yearLevel: string): Promise<void> {
        this.tester.isGoingTo(`Filter users by year level "${yearLevel || "Any"}"`);
        await this.page.getByTestId(UserBulkModify.DATA_TEST_YEAR_FILTER).selectOption(yearLevel);
    }

    /**
     * Select the token filter.
     * @param token - Token filter value.
     */
    async filterByToken(token: "1" | "0" | ""): Promise<void> {
        this.tester.isGoingTo(`Filter users by token "${token || "Any"}"`);
        await this.page.getByTestId(UserBulkModify.DATA_TEST_TOKEN_FILTER).selectOption(token);
    }

    /**
     * Select the ePortfolio filter.
     * @param eportfolio - ePortfolio filter value.
     */
    async filterByEportfolio(eportfolio: "1" | "0" | ""): Promise<void> {
        this.tester.isGoingTo(`Filter users by ePortfolio "${eportfolio || "Any"}"`);
        await this.page.getByTestId(UserBulkModify.DATA_TEST_EPORTFOLIO_FILTER).selectOption(eportfolio);
    }

    /**
     * Reset all filters and search text.
     */
    async resetFilters(): Promise<void> {
        this.tester.isGoingTo("Reset search and filters");
        await this.clearSearch();
        await this.filterByCampus("");
        await this.filterByRole("");
        await this.filterByYearLevel("");
        await this.filterByToken("");
        await this.filterByEportfolio("");
    }

    /**
     * Confirm how many user rows are visible in the Vue table.
     * @param expectedRows - Expected visible user row count.
     */
    async expectVisibleRows(expectedRows: number): Promise<void> {
        await expect(this.getRows()).toHaveCount(expectedRows);
    }

    /**
     * Confirm the Vue table pagination summary text.
     * @param expectedText - Expected pagination summary.
     */
    async expectPaginationText(expectedText: string): Promise<void> {
        await expect(this.page.locator(".VuePagination__count")).toHaveText(expectedText);
    }

    /**
     * Confirm the Vue table empty state is visible.
     */
    async expectNoMatchingUsers(): Promise<void> {
        await expect(this.getRows()).toHaveCount(0);
        await expect(this.page.getByText("No matching users...")).toBeVisible();
    }

    /**
     * Select the first visible user row.
     */
    async selectFirstUser(): Promise<void> {
        this.tester.isGoingTo("Select the first visible user");
        await this.page.locator(".vt-select-row").first().check();
    }

    /**
     * Click the delete users button.
     */
    async clickDeleteButton(): Promise<void> {
        this.tester.isGoingTo("Click the delete users button");
        await this.page.getByTestId(UserBulkModify.DATA_TEST_DELETE_BUTTON).click();
    }

    /**
     * Sort a column ascending.
     * @param columnName - Column heading.
     */
    async sortColumnAscending(columnName: string): Promise<void> {
        await this.sortColumn(columnName, "asc");
    }

    /**
     * Sort a column descending.
     * @param columnName - Column heading.
     */
    async sortColumnDescending(columnName: string): Promise<void> {
        await this.sortColumn(columnName, "desc");
    }

    /**
     * Confirm the value of a cell in the first visible row.
     * @param columnIndex - Zero-based column index including the selection checkbox column.
     * @param expectedText - Expected cell text.
     */
    async expectTopRowColumnText(columnIndex: number, expectedText: string): Promise<void> {
        await expect(this.getFirstRowCell(columnIndex)).toHaveText(expectedText);
    }

    private async sortColumn(columnName: string, direction: "asc" | "desc"): Promise<void> {
        this.tester.isGoingTo(`Sort the ${columnName} column ${direction === "asc" ? "ascending" : "descending"}`);
        const columnHeader = this.page
            .locator(".VueTables__heading", { hasText: new RegExp(`^${columnName}$`) })
            .locator("xpath=ancestor::th[1]");
        const sortedClass = new RegExp(`-sorted-${direction}`);

        await columnHeader.click();
        if (direction === "desc") {
            await columnHeader.click();
        }

        await expect(columnHeader).toHaveClass(sortedClass);
    }

    private getRows(): Locator {
        return this.page.locator("tbody tr.VueTables__row td.VueTables__select-row");
    }

    private getFirstRowCell(columnIndex: number): Locator {
        return this.page.locator("tbody tr.VueTables__row").first().locator("td").nth(columnIndex);
    }
}

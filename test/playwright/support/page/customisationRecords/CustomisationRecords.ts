import { expect, type Locator, type Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class CustomisationRecords {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/customisations/records";

    static readonly DATA_TEST_APPLY_FILTERS_BUTTON = "panel-apply-filters-button";

    static readonly DATA_TEST_FIRST_USER_CELL = "lazy-grid-row-cell-0-0-user";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    async goto(): Promise<void> {
        this.tester.isGoingTo("Visit the customisation records page");
        await this.page.goto(CustomisationRecords.URL);
    }

    async removeAllAppliedFilters(): Promise<void> {
        this.tester.isGoingTo("Remove all applied filters from the filter panel");
        const removeFilterButtons = this.page.getByTitle("Remove filter");

        while ((await removeFilterButtons.count()) > 0) {
            await removeFilterButtons.first().click();
        }
    }

    async applyFilters(): Promise<void> {
        this.tester.isGoingTo("Apply the current filters");
        await this.page.getByTestId(CustomisationRecords.DATA_TEST_APPLY_FILTERS_BUTTON).click();
    }

    async expectGridVisible(): Promise<void> {
        await expect(this.page.getByTestId(CustomisationRecords.DATA_TEST_FIRST_USER_CELL)).toBeVisible();
    }

    async expectGridRecordCount(count: number): Promise<void> {
        await expect(this.page.locator('tr[data-test^="lazy-grid-row-"]')).toHaveCount(count);
    }

    async expectFirstUserDetailsVisible(values: string[]): Promise<void> {
        const firstUserCell = this.getFirstUserCell();

        await expect(firstUserCell.locator("img")).toBeVisible();

        for (const value of values) {
            await expect(firstUserCell).toContainText(value);
        }
    }

    private getFirstUserCell(): Locator {
        return this.page.getByTestId(CustomisationRecords.DATA_TEST_FIRST_USER_CELL);
    }
}

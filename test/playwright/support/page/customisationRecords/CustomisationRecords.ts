import { expect, type Locator, type Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import Common from "@schoolboxTest/playwright/support/page/Common";

export default class CustomisationRecords {
    private tester: AcceptanceTester;

    private page: Page;

    private common: Common;

    static readonly URL = "/customisations/records";

    static readonly DATA_TEST_FIRST_USER_CELL = "lazy-grid-row-cell-0-0-user";

    static readonly DATA_TEST_RECORDS_CONTAINER = "customisation-records-container";

    static readonly DATA_TEST_SORT_BUTTON = "lazy-grid-sort-button-{columnKey}";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
        this.common = new Common(page);
    }

    async goto(): Promise<void> {
        this.tester.isGoingTo("Visit the customisation records page");
        await this.page.goto(CustomisationRecords.URL);
    }

    async removeAllAppliedFilters(): Promise<void> {
        this.tester.isGoingTo("Remove all applied filters from the filter panel");
        await this.common.removeAppliedFilters();
    }

    async applyFilters(): Promise<void> {
        this.tester.isGoingTo("Apply the current filters");
        await this.common.applyFilters();
    }

    async expectCsvDownload(expectedFileName: string): Promise<void> {
        const [download] = await Promise.all([
            this.page.waitForEvent("download"),
            this.page.getByRole("link", { name: "Export to CSV" }).click(),
        ]);

        await expect(download.failure()).resolves.toBeNull();
        expect(download.suggestedFilename()).toBe(expectedFileName);
    }

    async expectGridVisible(): Promise<void> {
        await expect(this.page.getByTestId(CustomisationRecords.DATA_TEST_FIRST_USER_CELL)).toBeVisible();
    }

    async expectGridRecordCount(count: number): Promise<void> {
        await expect(this.page.locator('tr[data-test^="lazy-grid-row-"]')).toHaveCount(count);
    }

    async expectPaginationSummary(start: number, end: number, total: number): Promise<void> {
        await expect(this.page.locator(".cursor-pagination b")).toHaveText(`${start}-${end} of ${total}`);
    }

    async clickFirstRowColumnLink(columnKey: string): Promise<void> {
        const link = this.page.locator(`tr[data-test^="lazy-grid-row-"] [data-test$="-${columnKey}"] a`).first();

        await link.scrollIntoViewIfNeeded();
        await expect(link).toBeVisible();
        await link.click();
    }

    async clickRowColumnLink(rowText: string, columnKey: string): Promise<void> {
        const link = this.page
            .locator('tr[data-test^="lazy-grid-row-"]')
            .filter({ hasText: rowText })
            .locator(`[data-test$="-${columnKey}"] a`)
            .first();

        await link.scrollIntoViewIfNeeded();
        await expect(link).toBeVisible();
        await link.click();
    }

    async sortColumn(columnKey: string, clicks: number = 1): Promise<void> {
        const sortButton = CustomisationRecords.DATA_TEST_SORT_BUTTON.replace("{columnKey}", columnKey);

        for (let index = 0; index < clicks; index += 1) {
            await this.page
                .getByTestId(CustomisationRecords.DATA_TEST_RECORDS_CONTAINER)
                .getByTestId(sortButton)
                .click();
        }
    }

    async expectFirstRowColumnToContain(columnKey: string, value: string): Promise<void> {
        await expect(this.getRowCell(0, columnKey)).toContainText(value);
    }

    async expectGridRowVisibleWithValues(values: string[]): Promise<void> {
        const row = this.page.locator('tr[data-test^="lazy-grid-row-"]').filter({ hasText: values[0] });

        await expect(row).toBeVisible();

        for (const value of values) {
            await expect(row).toContainText(value);
        }
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

    private getRowCell(rowIndex: number, columnKey: string): Locator {
        const columnIndexMap: Record<string, number> = {
            user: 0,
            created_at: 1,
            class: 2,
            unit: 3,
            type: 4,
        };

        return this.page.getByTestId(`lazy-grid-row-cell-${rowIndex}-${columnIndexMap[columnKey]}-${columnKey}`);
    }
}

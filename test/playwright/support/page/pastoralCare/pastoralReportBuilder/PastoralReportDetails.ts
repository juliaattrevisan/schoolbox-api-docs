import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";

export default class PastoralReportDetails {
    private page: Page;

    static readonly URL = "/pastoral/report/results/{id}";

    static readonly DATA_TEST_PASTORAL_TAB = `pastoral-report-{tabName}`;

    static readonly DATA_TEST_DOWNLOAD_BUTTON = `pastoral-report-download-button`;

    static readonly DATA_TEST_MODAL_TABLE_RECORD = "pr-table-modal-records";

    static readonly DATA_TEST_MODAL_RECORDS_ROWS = "pastoral-records-modal-list";

    static readonly RECORDS_POINTS_RADIO_BUTTON = `label[for='{radio}']`;

    static readonly POINTS_RADIO_BUTTON = "label[for='points']";

    static readonly RECORDS_RADIO_BUTTON = "label[for='records']";

    static readonly DATA_TEST_CLOSE_MODAL_BUTTON = "basic-modal-close-button";

    static readonly DATA_TEST_SORT_BUTTON = "pr-table-header-sort-button-{headerName}";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to the pastoral report details page
     * @param id - The report id
     */
    async goto(id: number): Promise<void> {
        await this.page.goto(PastoralReportDetails.URL.replace("{id}", id.toString()));
    }

    /**
     * clicks a tab in the pastoral report details page
     * @param tabName - The name of the tab
     */
    async clickTab(tabName: string): Promise<void> {
        const tabLocator: Locator = this.page.getByTestId(
            PastoralReportDetails.DATA_TEST_PASTORAL_TAB.replace("{tabName}", tabName),
        );
        await tabLocator.click();
    }

    /**
     * clicks the download button in the pastoral report details page
     */
    async clickDownloadButton(): Promise<void> {
        await this.page.getByTestId(PastoralReportDetails.DATA_TEST_DOWNLOAD_BUTTON).click();
    }

    /**
     * clicks the records or radio button in the pastoral report details page
     * @param button - The button to click: "points" or "records"
     */
    async clickRecordsOrPointsRadioButton(button: "points" | "records"): Promise<void> {
        const radioButton: Locator = this.page.locator(
            PastoralReportDetails.RECORDS_POINTS_RADIO_BUTTON.replace("{radio}", button),
        );
        await radioButton.click();
    }

    /**
     * checks that 1 or more tabs are presented in the pastoral report details page
     * @param tabNames - The names of the tabs: "List", "Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week",
     */
    async checkTabsArePresented(tabNames: string[]): Promise<void> {
        for (const tabName of tabNames) {
            const tabLocator = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_PASTORAL_TAB.replace("{tabName}", tabName),
            );
            await expect(tabLocator).toBeVisible();
        }
    }

    /**
     * checks that 1 or more tabs are not presented in the pastoral report details page
     * @param tabNames - The names of the tabs:"List", "Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week"
     */
    async checkTabsAreNotPresented(tabNames: string[]): Promise<void> {
        for (const tabName of tabNames) {
            const tabLocator = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_PASTORAL_TAB.replace("{tabName}", tabName),
            );
            await expect(tabLocator).not.toBeVisible();
        }
    }

    /**
     * clicks each tab in the pastoral report details page
     * checks the number of rows in the table presented in each tab
     * @param tabNames - The names of the tabs: "List", "Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week",
     * @param expectedRowCounts - The expected number of rows in each tab table
     */
    async clickTabsAndCheckRowCounts(tabNames: string[], expectedRowCounts: number[]): Promise<void> {
        for (let i = 0; i < tabNames.length; i += 1) {
            const tabName = tabNames[i];
            const expectedRowCount = expectedRowCounts[i];
            const tabLocator = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_PASTORAL_TAB.replace("{tabName}", tabName),
            );
            await tabLocator.click();
            const tableLocator = this.page.getByTestId("pr-table-container");
            await expect(tableLocator.getByTestId("pr-table-data-row")).toHaveCount(expectedRowCount);
        }
    }

    /**
     * check the number of records in the list tab
     * @param expectedCount - The expected number of records in the list tab
     */
    async checkListTabRecordCount(expectedCount: number): Promise<void> {
        const listTabLocator = this.page.getByTestId("pastoral-report-List");
        await listTabLocator.click();
        const listLocator = this.page.getByTestId("pr-list");
        await expect(listLocator.getByTestId("pr-list-record-rows")).toHaveCount(expectedCount);
    }

    /**
     * check the record is presenting the proper title, severity and number of points
     * @param recordId - The id of the record
     * @param studentId - The id of the student
     * @param expectedTitle - The expected title of the record
     * @param expectedSeverity - The expected severity of the record: Low, Critical, Medium, High
     * @param expectedPoints - The expected number of points of the record
     */
    async checkRecordDetails(
        recordId: number,
        studentId: number,
        expectedTitle: string,
        expectedSeverity: string,
        expectedPoints: string,
    ): Promise<void> {
        const rowLocator = this.page.locator(`#record${recordId}`);
        await expect(rowLocator.locator(`a[href="/pastoral/student/${studentId}/record/${recordId}"]`)).toContainText(
            expectedTitle,
        );
        await expect(rowLocator.locator(`span[title="${expectedSeverity}"]`)).toBeVisible();
        await expect(rowLocator.locator(`a[href="/pastoral/student/${studentId}/record/${recordId}"]`)).toContainText(
            ` ${expectedPoints}`,
        );
        await expect(rowLocator.locator("p.meta")).toBeVisible();
        await expect(rowLocator.locator("img")).toBeVisible();
    }

    /**
     * clicks a record in the list tab
     * @param recordId - The id of the record
     * @param studentId - The id of the student
     */
    async clickRecordInListTab(recordId: number, studentId: number): Promise<void> {
        await this.page.locator(`a[href="/pastoral/student/${studentId}/record/${recordId}"]`).click();
    }

    /**
     * for one or more report tabs
     * clicks a report tab, clicks one or more cells in the report table to open the modal, checks the number of records presented in the modal opened, close the modal
     * @param tabNames - The names of the tabs: "List", "Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week",
     * @param rowNumbers - The row to locate in the table: 1 for first row, 2 for second row, etc
     * @param tableCells - The table cell columns to click in the located rows
     * @param expectedCounts - The expected number of records in the modal for each tab
     */
    async clickTabAndCheckModalRecordCounts(
        tabNames: string[],
        rowNumbers: number[],
        tableCells: string[],
        expectedCounts: number[],
    ): Promise<void> {
        for (let i = 0; i < tabNames.length; i += 1) {
            const tabName = tabNames[i];
            const rowNumber = rowNumbers[i];
            const tableCell = tableCells[i];
            const expectedCount = expectedCounts[i];
            const tabLocator = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_PASTORAL_TAB.replace("{tabName}", tabName),
            );
            await tabLocator.click();

            // finds the row and clicks a cell column in that row
            const row = this.page.locator(`table tbody tr:nth-child(${rowNumber})`);
            await row.getByTestId(`pr-table-data-cell-${tableCell}`).click();

            // by clicking the cell, a modal opens - locating the modal and counting the records rows
            const modalTableLocator = this.page.getByTestId(PastoralReportDetails.DATA_TEST_MODAL_TABLE_RECORD);
            await expect(modalTableLocator.getByTestId(PastoralReportDetails.DATA_TEST_MODAL_RECORDS_ROWS)).toHaveCount(
                expectedCount,
            );

            // closing the modal
            const closeButton = this.page.getByTestId(PastoralReportDetails.DATA_TEST_CLOSE_MODAL_BUTTON);
            await closeButton.click();
        }
    }

    /**
     * for one or more report tabs
     * clicks a report tab, check the number of records is presented properly in the table
     * @param tabNames - The names of the tabs: "List", "Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week",
     * @param rowNumbers - The row to locate in the table: 1 for first row, 2 for second row, etc
     * @param tableCells - The table cell columns to click in the located rows
     * @param expectedCounts - The expected number of records in the modal for each tab
     */
    async checkRowCounts(
        tabNames: string[],
        rowNumbers: number[],
        tableCells: string[],
        expectedCounts: string[],
    ): Promise<void> {
        for (let i = 0; i < tabNames.length; i += 1) {
            const tabName = tabNames[i];
            const rowNumber = rowNumbers[i];
            const tableCell = tableCells[i];
            const expectedCount = expectedCounts[i];

            const tabLocator = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_PASTORAL_TAB.replace("{tabName}", tabName),
            );
            await tabLocator.click();

            // finds the row and locate a cell column in that row - checks the correct number is presented
            const row = this.page.locator(`table tbody tr:nth-child(${rowNumber})`);
            await expect(row.getByTestId(`pr-table-data-cell-${tableCell}`)).toHaveText(expectedCount);
        }
    }

    /**
     * click the close modal button
     */
    async closeModal(): Promise<void> {
        const closeButton = this.page.getByTestId(PastoralReportDetails.DATA_TEST_CLOSE_MODAL_BUTTON);
        await closeButton.click();
    }

    /**
     * clicks each tab, sorts a column and checks the sorting works properly
     * @param tabNames - The name of the tab: "List", "Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week",
     * @param headerNames - The name of the header to sort: user, oct_2017, total, etc
     * @param columnNames - The column name to check the expected data
     * @param rowNumbers - The row to locate in the table: 1 for first row, 2 for second row, etc
     * @param expectedTexts - The expected text in the first row after sorting
     */
    async sortColumnAndCheck(
        tabNames: string[],
        headerNames: string[],
        columnNames: string[],
        rowNumbers: number[],
        expectedTexts: string[],
    ): Promise<void> {
        for (let i = 0; i < tabNames.length; i += 1) {
            const tabName = tabNames[i];
            const headerName = headerNames[i];
            const columnName = columnNames[i];
            const rowNumber = rowNumbers[i];
            const expectedText = expectedTexts[i];

            const tabLocator = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_PASTORAL_TAB.replace("{tabName}", tabName),
            );
            await tabLocator.click();
            const sortButton = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_SORT_BUTTON.replace("{headerName}", headerName),
            );
            await sortButton.click();

            // finds the row and locate a cell column in that row - checks the correct number is presented
            const row = this.page.locator(`table tbody tr:nth-child(${rowNumber})`);
            await expect(row.getByTestId(`pr-table-data-cell-${columnName}`)).toHaveText(expectedText);
        }
    }

    /**
     * for one or more report tabs
     * clicks the report tab, counts the number of data test sort buttons, check whether some type or dates are presented or not
     * @param tabNames - The names of the tabs: "List", "Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week",
     * @param expectedSortButtonCounts - The expected number of sort buttons in each tab
     * @param typesToCheckPresent - The types to check are presented in each tab: eg: 1 for Award/Achievement, 7 for Behavioural Issues, etc
     * @param typesToCheckNotPresent - The types to check are not presented in each tab: eg: 1 for Award/Achievement, 7 for Behavioural Issues, etc
     * @param dateToCheckPresent - The dates to check are presented in each tab: eg: oct_2017, week_2, etc
     * @param dateToCheckNotPresent - The dates to check are not presented in each tab: eg: oct_2017, week_2, etc
     */
    async checkSortButtonsAndTypesDatesPresence(
        tabNames: string[],
        expectedSortButtonCounts: number[],
        typesToCheckPresent: string[][],
        typesToCheckNotPresent: string[][],
        dateToCheckPresent: string[][],
        dateToCheckNotPresent: string[][],
    ): Promise<void> {
        for (let i = 0; i < tabNames.length; i += 1) {
            const tabName = tabNames[i];
            const expectedSortButtonCount = expectedSortButtonCounts[i];
            const typesPresent = typesToCheckPresent[i];
            const typesNotPresent = typesToCheckNotPresent[i];
            const datePresent = dateToCheckPresent[i];
            const dateNotPresent = dateToCheckNotPresent[i];

            const tabLocator = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_PASTORAL_TAB.replace("{tabName}", tabName),
            );
            await tabLocator.click();
            const sortButtonsLocator = this.page.getByTestId(/^pr-table-header-sort-button/);
            await expect(sortButtonsLocator).toHaveCount(expectedSortButtonCount);

            for (const type of typesPresent) {
                const typeLocator = this.page.getByTestId(
                    PastoralReportDetails.DATA_TEST_SORT_BUTTON.replace("{headerName}", `count_subtype_${type}`),
                );
                await expect(typeLocator).toBeVisible();
            }

            for (const type of typesNotPresent) {
                const typeLocator = this.page.getByTestId(
                    PastoralReportDetails.DATA_TEST_SORT_BUTTON.replace("{headerName}", `count_subtype_${type}`),
                );
                await expect(typeLocator).not.toBeVisible();
            }
            for (const date of datePresent) {
                const dateLocator = this.page.getByTestId(
                    PastoralReportDetails.DATA_TEST_SORT_BUTTON.replace("{headerName}", date),
                );
                await expect(dateLocator).toBeVisible();
            }
            for (const date of dateNotPresent) {
                const dateLocator = this.page.getByTestId(
                    PastoralReportDetails.DATA_TEST_SORT_BUTTON.replace("{headerName}", date),
                );
                await expect(dateLocator).not.toBeVisible();
            }
        }
    }
}

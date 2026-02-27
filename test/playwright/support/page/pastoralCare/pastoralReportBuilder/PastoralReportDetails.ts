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
     * check the record is presenting the proper title, severity,number of points student picture and meta information for a record
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
     * clicks a cell in the table in the pastoral report table and checks the modal open
     * @param tableLocator - Object with trIndex (row number) and tdIndex (column number) to locate the cell
     * @param tableLocator.trIndex - The row number in the table
     * @param tableLocator.tdIndex - The column number in the table
     */
    async clickCell(tableLocator: { trIndex: number; tdIndex: number }): Promise<void> {
        const tableCellLocator = this.page.locator(
            `table tbody tr:nth-child(${tableLocator.trIndex}) td:nth-child(${tableLocator.tdIndex})`,
        );
        await tableCellLocator.click();

        const modalLocator = this.page.getByTestId(PastoralReportDetails.DATA_TEST_MODAL_TABLE_RECORD);
        await expect(modalLocator).toBeVisible();
    }

    /**
     * locates the modal and checks the number of records rows in the modal
     * @param expectedCount - The expected number of records in the modal
     */
    async checkModalRowRecordCount(expectedCount: number): Promise<void> {
        const modalTableLocator = this.page.getByTestId(PastoralReportDetails.DATA_TEST_MODAL_TABLE_RECORD);
        await expect(modalTableLocator.getByTestId(PastoralReportDetails.DATA_TEST_MODAL_RECORDS_ROWS)).toHaveCount(
            expectedCount,
        );
    }

    /**
     * check the modal is presenting the proper title, severity, number of points, student picture and meta information for a record
     * @param recordId - The id of the record
     * @param studentId - The id of the student
     * @param expectedTitle - The expected title of the record
     * @param expectedSeverity - The expected severity of the record: Low, Critical, Medium, High
     * @param expectedPoints - The expected number of points of the record
     */
    async checkModalRecordDetails(
        recordId: number,
        studentId: number,
        expectedTitle: string,
        expectedSeverity: string,
        expectedPoints: string,
    ): Promise<void> {
        const modalTableLocator = this.page.getByTestId(PastoralReportDetails.DATA_TEST_MODAL_TABLE_RECORD);
        const rowLocator = modalTableLocator.locator(`#record${recordId}`);
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
     * check the public body of the pastoral record is presented in the modal
     * @param expectedBody - The expected public body text of the pastoral record
     */
    async checkModalPublicBodyPresented(expectedBody: string): Promise<void> {
        const bodyLocator = this.page.locator(`div[data-test="pr-table-modal-records"]`, {
            hasText: expectedBody,
        });
        await expect(bodyLocator).toBeVisible();
    }

    /**
     * check the private body of the pastoral record is not presented in the modal
     * @param expectedBody - The expected private body text of the pastoral record
     */
    async checkModalPrivateBodyNotPresented(expectedBody: string): Promise<void> {
        const bodyLocator = this.page.locator(`div[data-test="pr-table-modal-records"]`, {
            hasText: expectedBody,
        });
        await expect(bodyLocator).not.toBeVisible();
    }

    /**
     * check the cell in the table is showing the proper number of records
     * @param tableLocator - Object with trIndex (row number) and tdIndex (column number) to locate the cell
     * @param tableLocator.trIndex - The row number in the table
     * @param tableLocator.tdIndex - The column number in the table
     * @param expectedCount - The expected number of records in the cell
     */
    async checkRecordCount(tableLocator: { trIndex: number; tdIndex: number }, expectedCount: number): Promise<void> {
        const tableCellLocator = this.page.locator(
            `table tbody tr:nth-child(${tableLocator.trIndex}) td:nth-child(${tableLocator.tdIndex})`,
        );
        await expect(tableCellLocator).toHaveText(expectedCount.toString());
    }

    /**
     * click the close modal button
     */
    async closeModal(): Promise<void> {
        const closeButton = this.page.getByTestId(PastoralReportDetails.DATA_TEST_CLOSE_MODAL_BUTTON);
        await closeButton.click();
    }

    /**
     * clicks a header to sort the column
     * @param headerName - The name of the header to sort: user, oct_2017, total, etc
     */
    async clickHeaderName(headerName: string): Promise<void> {
        const sortButton = this.page.getByTestId(
            PastoralReportDetails.DATA_TEST_SORT_BUTTON.replace("{headerName}", headerName),
        );
        await sortButton.click();
    }

    /**
     * sorts a column and checks the sorting works properly
     * @param headerName - The name of the header to sort: user, oct_2017, total, etc
     * @param tableLocator - Object with trIndex (row number) and tdIndex (column number) to locate the cell
     * @param tableLocator.trIndex - The row number in the table
     * @param tableLocator.tdIndex - The column number in the table
     * @param expectedText - The expected text in the specified cell after sorting
     */
    async sortColumnAndCheck(
        headerName: string,
        tableLocator: { trIndex: number; tdIndex: number },
        expectedText: string,
    ): Promise<void> {
        await this.clickHeaderName(headerName);
        const tableCellLocator = this.page.locator(
            `table tbody tr:nth-child(${tableLocator.trIndex}) td:nth-child(${tableLocator.tdIndex})`,
        );
        await expect(tableCellLocator).toHaveText(expectedText);
    }

    /**
     * check the number of headers that are presented on the table in a report tab
     * @param expectedCount - The expected number of headers in the table
     */
    async checkHeaderCount(expectedCount: number): Promise<void> {
        const sortButtonsLocator = this.page.getByTestId(/^pr-table-header-sort-button/);
        await expect(sortButtonsLocator).toHaveCount(expectedCount);
    }

    /**
     * check if the header is presented in the table in a report tab
     * @param typesPresent - The types to check are presented in each tab: eg: 1 for Award/Achievement, 7 for Behavioural Issues, etc
     */
    async checkHeaderPresented(typesPresent: string[]): Promise<void> {
        for (const type of typesPresent) {
            const typeLocator = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_SORT_BUTTON.replace("{headerName}", `count_subtype_${type}`),
            );
            await expect(typeLocator).toBeVisible();
        }
    }

    /**
     * check if the header is not presented in the table in a report tab
     * @param typesNotPresent - The types to check are presented in each tab: eg: 1 for Award/Achievement, 7 for Behavioural Issues, etc
     */
    async checkHeaderNotPresented(typesNotPresent: string[]): Promise<void> {
        for (const type of typesNotPresent) {
            const typeLocator = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_SORT_BUTTON.replace("{headerName}", `count_subtype_${type}`),
            );
            await expect(typeLocator).not.toBeVisible();
        }
    }

    /**
     * check the date header is presented in the table in a report tab
     * @param dateToCheckPresent - The dates to check are presented in each tab: eg: oct_2017, week_2, etc
     */
    async checkDateHeaderPresented(dateToCheckPresent: string[]): Promise<void> {
        for (const date of dateToCheckPresent) {
            const dateLocator = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_SORT_BUTTON.replace("{headerName}", date),
            );
            await expect(dateLocator).toBeVisible();
        }
    }

    /**
     * check the date header is not presented in the table in a report tab
     * @param dateToCheckNotPresent - The dates to check are not presented in each tab: eg: oct_2017, week_2, etc
     */
    async checkDateHeaderNotPresented(dateToCheckNotPresent: string[]): Promise<void> {
        for (const date of dateToCheckNotPresent) {
            const dateLocator = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_SORT_BUTTON.replace("{headerName}", date),
            );
            await expect(dateLocator).not.toBeVisible();
        }
    }

    /**
     * check the number of tags presented in the report
     * @param tagCount - The expected number of tags presented in the report
     */
    async checkTagCount(tagCount: number): Promise<void> {
        const tagLocator = this.page.locator(`a[href^="/pastoral/report/results/tag/"]`);
        await expect(tagLocator).toHaveCount(tagCount);
    }

    /**
     * check the public body of the pastoral record is presented in the report
     * @param expectedBody - The expected public body text of the pastoral record
     */
    async checkPublicBodyPresented(expectedBody: string): Promise<void> {
        const bodyLocator = this.page.locator(`div[data-test="pr-list"]`, { hasText: expectedBody });
        await expect(bodyLocator).toBeVisible();
    }

    /**
     * check the private body of the pastoral record is not presented in the report
     * @param expectedBody - The expected private body text of the pastoral record
     */
    async checkPrivateBodyNotPresented(expectedBody: string): Promise<void> {
        const bodyLocator = this.page.locator(`div[data-test="pr-list"]`, { hasText: expectedBody });
        await expect(bodyLocator).not.toBeVisible();
    }

    /**
     * check the pastoral attachment is presented in the report
     */
    async checkAttachmentPresented(): Promise<void> {
        const attachmentLocator = this.page.getByTestId(`pastoral-record-attachment`);
        await expect(attachmentLocator).toBeVisible();
    }

    /**
     * check the pastoral record comment is presented in the report
     * @param expectedComment - The expected comment text
     */
    async checkCommentPresented(expectedComment: string): Promise<void> {
        const commentLocator = this.page.locator(`ul.information-list.reply`, { hasText: expectedComment });
        await expect(commentLocator).toBeVisible();
    }

    /**
     * check the pastoral record comment is not presented in the report
     * @param expectedComment - The expected comment text
     */
    async checkCommentNotPresented(expectedComment: string): Promise<void> {
        const commentLocator = this.page.locator(`ul.information-list.reply`, { hasText: expectedComment });
        await expect(commentLocator).not.toBeVisible();
    }
}

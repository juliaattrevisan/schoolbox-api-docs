import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";

export default class PastoralReportDetails {
    private page: Page;

    static readonly URL = "/pastoral/report/results/{id}";

    static readonly DATA_TEST_PASTORAL_TAB = `pastoral-report-{tabName}`;

    static readonly DATA_TEST_DOWNLOAD_BUTTON = `pastoral-report-download-button`;

    static readonly MODAL_TABLE_RECORD = "div.pr-table__records";

    static readonly MODAL_RECORDS_ROWS = "ul.information-list.threaded";

    static readonly RECORDS_POINTS_RADIO_BUTTON = `label[for='{radio}']`;

    static readonly POINTS_RADIO_BUTTON = "label[for='points']";

    static readonly RECORDS_RADIO_BUTTON = "label[for='records']";

    static readonly CLOSE_MODAL_BUTTON = "button.close-modal";

    static readonly DATA_TEST_SORT_BUTTON = "sort-button-{headerName}";

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
            await expect(tableLocator.locator("tr.pr-table-row")).toHaveCount(expectedRowCount);
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
        await expect(listLocator.locator("div.c-island")).toHaveCount(expectedCount);
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
     * clicks a cell in the report able to open the modal with the records details
     * check the number of records presented in the modal opened
     * @param childId1 - The child row number in the table to click
     * @param childId2 - The child column number in the table to click
     * @param expectedCount - The expected number of records in the modal
     */
    async checkModalRecordCount(childId1: number, childId2: number, expectedCount: number): Promise<void> {
        await this.page.locator(`table tbody tr:nth-child(${childId1}) td:nth-child(${childId2})`).click();
        const modalTableLocator = this.page.locator(PastoralReportDetails.MODAL_TABLE_RECORD);
        await expect(modalTableLocator.locator(PastoralReportDetails.MODAL_RECORDS_ROWS)).toHaveCount(expectedCount);
    }

    /**
     * for one or more report tabs
     * clicks a report tab, clicks one or more cells in the report table to open the modal, checks the number of records presented in the modal opened, close the modal
     * @param tabNames - The names of the tabs: "List", "Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week",
     * @param childIds1 - The child row numbers in the table to click
     * @param childIds2 - The child column numbers in the table to click
     * @param expectedCounts - The expected number of records in the modal for each tab
     */
    async clickTabAndCheckModalRecordCounts(
        tabNames: string[],
        childIds1: number[],
        childIds2: number[],
        expectedCounts: number[],
    ): Promise<void> {
        for (let i = 0; i < tabNames.length; i += 1) {
            const tabName = tabNames[i];
            const childId1 = childIds1[i];
            const childId2 = childIds2[i];
            const expectedCount = expectedCounts[i];
            const tabLocator = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_PASTORAL_TAB.replace("{tabName}", tabName),
            );
            await tabLocator.click();
            await this.page.locator(`table tbody tr:nth-child(${childId1}) td:nth-child(${childId2})`).click();
            const modalTableLocator = this.page.locator(PastoralReportDetails.MODAL_TABLE_RECORD);
            await expect(modalTableLocator.locator(PastoralReportDetails.MODAL_RECORDS_ROWS)).toHaveCount(
                expectedCount,
            );
            const closeButton = this.page.locator(PastoralReportDetails.CLOSE_MODAL_BUTTON);
            await closeButton.click();
        }
    }

    /**
     * for one or more report tabs
     * clicks a report tab, check the number of records is presented properly in the table
     * @param tabNames - The names of the tabs: "List", "Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week",
     * @param childIds1 - The child row numbers in the table to click
     * @param childIds2 - The child column numbers in the table to click
     * @param expectedCounts - The expected number in the rows in each tab table
     */
    async checkRowCounts(
        tabNames: string[],
        childIds1: number[],
        childIds2: number[],
        expectedCounts: string[],
    ): Promise<void> {
        for (let i = 0; i < tabNames.length; i += 1) {
            const tabName = tabNames[i];
            const childId1 = childIds1[i];
            const childId2 = childIds2[i];
            const expectedCount = expectedCounts[i];

            const tabLocator = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_PASTORAL_TAB.replace("{tabName}", tabName),
            );
            await tabLocator.click();
            const tableLocator = this.page.locator(`table tbody tr:nth-child(${childId1}) td:nth-child(${childId2})`);
            await expect(tableLocator).toHaveText(expectedCount);
        }
    }

    /**
     * click the close modal button
     */
    async closeModal(): Promise<void> {
        const closeButton = this.page.locator(PastoralReportDetails.CLOSE_MODAL_BUTTON);
        await closeButton.click();
    }

    /**
     * clicks each tab, sorts a column and checks the sorting works properly
     * @param tabNames - The name of the tab: "List", "Student by Type", "Type by Month", "Type by Week", "Student by Month", "Student by Week",
     * @param headerNames - The name of the header to sort: user, oct_2017, total, etc
     * @param childIds1 - The child row numbers in the table to click
     * @param childIds2 - The child column numbers in the table to click
     * @param expectedTexts - The expected text in the first row after sorting
     */
    async sortColumnAndCheck(
        tabNames: string[],
        headerNames: string[],
        childIds1: number[],
        childIds2: number[],
        expectedTexts: string[],
    ): Promise<void> {
        for (let i = 0; i < tabNames.length; i += 1) {
            const tabName = tabNames[i];
            const headerName = headerNames[i];
            const childId1 = childIds1[i];
            const childId2 = childIds2[i];
            const expectedText = expectedTexts[i];

            const tabLocator = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_PASTORAL_TAB.replace("{tabName}", tabName),
            );
            await tabLocator.click();
            const sortButton = this.page.getByTestId(
                PastoralReportDetails.DATA_TEST_SORT_BUTTON.replace("{headerName}", headerName),
            );
            await sortButton.click();
            const tableLocator = this.page.locator(`table tbody tr:nth-child(${childId1}) td:nth-child(${childId2})`);
            await expect(tableLocator).toHaveText(expectedText);
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
            const sortButtonsLocator = this.page.getByTestId(/^sort-button/);
            await expect(sortButtonsLocator).toHaveCount(expectedSortButtonCount);

            for (const type of typesPresent) {
                const typeLocator = this.page.getByTestId(`sort-button-count_subtype_${type}`);
                await expect(typeLocator).toBeVisible();
            }

            for (const type of typesNotPresent) {
                const typeLocator = this.page.getByTestId(`sort-button-count_subtype_${type}`);
                await expect(typeLocator).not.toBeVisible();
            }
            for (const date of datePresent) {
                const dateLocator = this.page.getByTestId(`sort-button-${date}`);
                await expect(dateLocator).toBeVisible();
            }
            for (const date of dateNotPresent) {
                const dateLocator = this.page.getByTestId(`sort-button-${date}`);
                await expect(dateLocator).not.toBeVisible();
            }
        }
    }
}

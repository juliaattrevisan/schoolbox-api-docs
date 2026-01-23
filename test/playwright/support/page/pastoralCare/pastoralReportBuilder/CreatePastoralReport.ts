import type { Page } from "@playwright/test";

export default class CreatePastoralReport {
    private page: Page;

    static readonly URL = "/pastoral/report/insert";

    static readonly URL_MODIFY = "/pastoral/report/update/{id}";

    static readonly REPORT_NAME = "input#name";

    static readonly FIXED_DATE_RADIO_BUTTON = "label[for='absolute-dates-option']";

    static readonly DATE_FROM = "input#datetime_after";

    static readonly DATE_TO = "input#datetime_before";

    static readonly SAVE_BUTTON = `input[value="Save Report"]`;

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to the pastoral report create page
     */
    async goto(): Promise<void> {
        await this.page.goto(CreatePastoralReport.URL);
    }

    /**
     * Navigates to the pastoral report modify page
     * @param id - The report id
     */
    async gotoModifyPage(id: number): Promise<void> {
        await this.page.goto(CreatePastoralReport.URL_MODIFY.replace("{id}", id.toString()));
    }

    /**
     * type a report title in the report name field
     * @param title - The report title
     */
    async fillReportNameField(title: string): Promise<void> {
        await this.page.fill(CreatePastoralReport.REPORT_NAME, title);
    }

    /**
     * select the fixed date radio button
     */
    async selectFixedDateRadioButton(): Promise<void> {
        await this.page.click(CreatePastoralReport.FIXED_DATE_RADIO_BUTTON);
    }

    /**
     * fill in the date from field
     * @param dateFrom - The date from value
     */
    async fillDateFromField(dateFrom: string): Promise<void> {
        await this.page.fill(CreatePastoralReport.DATE_FROM, dateFrom);
    }

    /**
     * fill in the date to field
     * @param dateTo - The date to value
     */
    async fillDateToField(dateTo: string): Promise<void> {
        await this.page.fill(CreatePastoralReport.DATE_TO, dateTo);
    }

    /**
     * select the frequency tables to be included in the report
     * @param frequencyType - The frequency type (e.g., "studentByType", "typeByMonth", "typeByWeek", "studentByMonth", "studentByWeek")
     */
    async selectFrequencyTables(frequencyType: string): Promise<void> {
        await this.page.click(`label[for="summary-tables-${frequencyType}"]`);
    }

    /**
     * select the type to be included in the report
     * @param type - The type (e.g., "Attendance", "Behaviour", "Wellbeing")
     */
    async selectType(type: string): Promise<void> {
        await this.page.click(`label[for="type-${type}"]`);
    }

    /**
     * click the save button
     */
    async clickSaveButton(): Promise<void> {
        await this.page.click(CreatePastoralReport.SAVE_BUTTON);
    }

    /**
     * create a pastoral
     * @param title - The report title
     * @param dateFrom - The date from value
     * @param dateTo - The date to value
     */
    // async createPastoralReport(title: string, dateFrom: string, dateTo: string): Promise<void> {
    //     await this.goto();
    //     await this.fillReportNameField(title);
    //     await this.selectFixedDateRadioButton();
    //     await this.fillDateFromField(dateFrom);
    //     await this.fillDateToField(dateTo);
    //     await this.clickSaveButton();
    // }

    /**
     * create a pastoral report by always passing a title
     * optionally passing a date range
     * optionally passing frequency tables to be included or excluded
     * optionally passing  types
     * @param title - The report title
     * @param dateFrom - The date from value
     * @param dateTo - The date to value
     * @param frequencyTables - The frequency tables to be included or excluded
     * @param types - The types to be included or excluded: 1 for academic, 2 for attendance, etc
     */
    async createPastoralReport(
        title: string,
        dateFrom?: string,
        dateTo?: string,
        frequencyTables?: string[],
        types?: string[],
    ): Promise<void> {
        await this.goto();
        await this.fillReportNameField(title);
        if (dateFrom && dateTo) {
            await this.selectFixedDateRadioButton();
            await this.fillDateFromField(dateFrom);
            await this.fillDateToField(dateTo);
        }
        if (types) {
            for (const type of types) {
                await this.selectType(type);
            }
        }
        if (frequencyTables) {
            for (const frequencyType of frequencyTables) {
                await this.selectFrequencyTables(frequencyType);
            }
        }
        await this.clickSaveButton();
    }
}

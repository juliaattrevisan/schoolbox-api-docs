import type { Page, Locator } from "@playwright/test";

export default class PastoralCareReports {
    private page: Page;

    static readonly URL = "/pastoral/report";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to the Pastoral Care Reports page
     */
    async goto(): Promise<void> {
        await this.page.goto(PastoralCareReports.URL);
    }

    /**
     * Click to open a pastoral report by its title
     * @param reportTitle - The title of the pastoral report
     * @returns A Locator for the opened pastoral report
     */
    async openPastoralReport(reportTitle: string): Promise<Locator> {
        const reportsSection = this.page.locator("#studentsList");
        const reportLocator = reportsSection.getByText(reportTitle);
        await reportLocator.click();
        return reportLocator;
    }
}

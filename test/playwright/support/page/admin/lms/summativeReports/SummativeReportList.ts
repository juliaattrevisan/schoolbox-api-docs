import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";

export default class SummativeReportList {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/adminv2/summative-report";

    static readonly EMPTY_STATE = "div.empty-state";

    static readonly BUTTON_CREATE_SUMMATIVE_REPORT = "Click here";

    static readonly DATA_TEST_SUMMATIVE_TITLE = `summative-report-title-{title}`;

    static readonly DATA_TEST_SUMMATIVE_ACTION_BUTTON = `summative-report-{action}-{title}`;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the summative report list page
     */
    async goto(): Promise<void> {
        await this.page.goto(SummativeReportList.URL);
    }

    /**
     * check the empty state is presented
     */
    async checkEmptyStateIsPresented(): Promise<void> {
        await expect(this.page.locator(SummativeReportList.EMPTY_STATE)).toBeVisible();
        await expect(this.page.locator(SummativeReportList.EMPTY_STATE)).toContainText(
            "No summative reports have been created.",
        );
        await expect(this.page.locator(SummativeReportList.EMPTY_STATE)).toContainText("to build a summative report.");
    }

    /**
     * click the create summative report button
     */
    async clickCreateSummativeReportButton(): Promise<void> {
        await this.page.getByText(SummativeReportList.BUTTON_CREATE_SUMMATIVE_REPORT).click();
    }

    /**
     * check summative report title is presented in the list
     * @param title - The title of the summative report
     */
    async checkSummativeReportTitleIsPresented(title: string): Promise<void> {
        const dataTestId = SummativeReportList.DATA_TEST_SUMMATIVE_TITLE.replace("{title}", title);
        await expect(this.page.getByTestId(dataTestId)).toContainText(title);
    }

    /**
     * click a buttons to action in the summative report list
     * @param action - The action to perform (preview, download, modify, copy, delete)
     * @param title - The title of the summative report
     */
    async clickSummativeReportActionButton(
        action: "preview" | "download" | "modify" | "copy" | "delete",
        title: string,
    ): Promise<void> {
        const dataTestId = SummativeReportList.DATA_TEST_SUMMATIVE_ACTION_BUTTON.replace("{action}", action).replace(
            "{title}",
            title,
        );
        await this.page.getByTestId(dataTestId).click();
    }
}

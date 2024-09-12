import path from "path";
import { expect, Page } from "@playwright/test";
import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class SubmitForm {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/assessments/{assessment_id}/{user_id}/submit";

    static readonly DATATEST_BUTTON_ASSESSMENT_CREATE_SUBMIT = "assessment-submission-create-submit";

    static readonly DATATEST_ASSESSMENT_SUMMARY_FILES_LIST_FILE = "assessment-summary-files-list-file";

    private filePath?: string;

    private contents?: string;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Go to the submit form for a given assessment and user
     * @param assessmentId - The assessment ID
     * @param userId - The user ID
     */
    async goto(assessmentId: number, userId: number): Promise<void> {
        const targetUrl = SubmitForm.URL.replace("{assessment_id}", assessmentId.toString()).replace(
            "{user_id}",
            userId.toString(),
        );
        await this.page.goto(targetUrl);
    }

    /**
     * Sets editor contents
     * @param contents - The contents to set in the editor
     */
    async setContents(contents: string): Promise<void> {
        await this.tester.fillCKEditorById("editor-body", contents);
        this.contents = contents;
    }

    /**
     * Attaches a file to the form
     * @param filePath - The file to attach
     */
    async setAttachment(filePath: string): Promise<void> {
        const attachZone = this.page.locator("#files-zone");
        await this.tester.dragAndDropFile(attachZone, filePath);
        this.filePath = filePath;
    }

    /**
     * Submits this form to create the section
     */
    async submit(): Promise<void> {
        await this.page.waitForTimeout(1000); // Wait for auto-save to finish
        await this.page.getByTestId(SubmitForm.DATATEST_BUTTON_ASSESSMENT_CREATE_SUBMIT).click();
        await expect(this.page.locator("h2.subheader").filter({ hasText: "SUBMISSION HISTORY" })).toBeVisible();

        // Verify if the contents are present
        if (this.contents) {
            await expect(
                this.page.getByTestId(SubmitForm.DATATEST_ASSESSMENT_SUMMARY_FILES_LIST_FILE).filter({
                    hasText: "Online Submission",
                }),
            ).toBeVisible();
        }

        // Verify if the file is present
        if (this.filePath) {
            await expect(
                this.page.getByTestId(SubmitForm.DATATEST_ASSESSMENT_SUMMARY_FILES_LIST_FILE).filter({
                    hasText: path.basename(this.filePath).split(".")[0],
                }),
            ).toBeVisible();
        }
    }
}

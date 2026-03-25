import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";

export default class CreateSummativeReport {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/adminv2/summative-report/create";

    static readonly TITLE = "input#report-title";

    static readonly SUMMARY_TOGGLE = `label[for="report-include-summary"]`;

    static readonly CLASS_TOGGLE = `label[for="report-include-class"]`;

    static readonly DATA_TEST_SUMMARY_HEADING = "report-summary-heading";

    static readonly SUMMARY_OVERALL_GRADE = `label[for="report-summary-show-overall-grade"]`;

    static readonly DATA_TEST_SUMMARY_WORK_TYPE_CHECKBOX = `report-summary-worktype-{workType}-{option}`;

    static readonly SUMMARY_CLASS_CODE = `label[for="report-summary-show-class-code"]`;

    static readonly CLASS_OVERALL_GRADE = `label[for="report-class-show-overall-grade"]`;

    static readonly CLASS_PAGE_CLASS_CODE = `label[for="report-class-show-class-code"]`;

    static readonly CLASS_PAGE_SUBJECT_OVERVIEW = `label[for="report-class-show-subject-overview"]`;

    static readonly DATA_TEST_CLASS_WORK_TYPE_CHECKBOX = `report-class-worktype-{workType}-{option}`;

    static readonly ENABLE_SUMMARY_CURRICULUM_PROGRESSION = `label[for="report-summary-enable-vic-progression"]`;

    static readonly SUMMARY_CURRICULUM_PROGRESSION_TITLE = "input#report-summary-vic-progression-title";

    static readonly ENABLE_CLASS_CURRICULUM_PROGRESSION = `label[for="report-class-enable-vic-progression"]`;

    static readonly CLASS_CURRICULUM_PROGRESSION_TITLE = "input#report-class-vic-progression-title";

    static readonly BUTTON_SAVE = `input[value="Save"]`;

    static readonly TITLE_ERROR = "#report-title-error";

    static readonly PERIODS_ERROR = "#report-terms-error";

    static readonly STUDENTS_ERROR = "#report-year-levels-error";

    static readonly SUMMARY_CURRICULUM_PROGRESSION_TITLE_ERROR = "#report-summary-vic-progression-title-error";

    static readonly CLASS_CURRICULUM_PROGRESSION_TITLE_ERROR = "#report-class-vic-progression-title-error";

    private filePath?: string;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the create summative report page
     */
    async goto(): Promise<void> {
        await this.page.goto(CreateSummativeReport.URL);
    }

    /**
     * click the save button
     */
    async clickSaveButton(): Promise<void> {
        await this.page.click(CreateSummativeReport.BUTTON_SAVE);
    }

    /**
     * fill in the title field
     * @param title - The title to fill in
     */
    async fillTitleField(title: string): Promise<void> {
        await this.page.fill(CreateSummativeReport.TITLE, title);
    }

    /**
     * fill feature image
     * @param db - The DatabaseHelper instance
     * @param filePath - The filename to fill
     */
    async attachLogoImage(db: DatabaseHelper, filePath: string): Promise<void> {
        const attachZone = this.page.locator("#LogoZone");
        await this.tester.dragAndDropFile(db, attachZone, filePath);
    }

    /**
     * fill the introductory message field
     * @param message - The message to fill in
     */
    async fillIntroductoryMessageField(message: string): Promise<void> {
        await this.tester.fillCKEditorById("report-introduction", message);
    }

    /**
     * fill in the periods field
     * @param period - The period to fill in
     */
    async fillPeriodsField(period: string): Promise<void> {
        await this.page.locator(`input[id="report-terms-selectized"]`).click();
        await this.page.keyboard.type(period, { delay: 500 });
        await this.page.locator("[data-selectable]").first().filter({ hasText: period }).click();
    }

    /**
     * fill in the year level, campus or student field
     * @param studentSelection - The field to fill in: campuses, year-levels or students
     * @param data - The data to fill in
     */
    async fillStudentSelectionField(studentSelection: string, data: string): Promise<void> {
        await this.page.locator(`input[id="report-${studentSelection}-selectized"]`).click();
        await this.page.keyboard.type(data, { delay: 500 });
        await this.page.keyboard.press("Enter");
    }

    /**
     * delete data from the student selection field
     * @param studentSelection - The field to delete data from: campuses, year-levels or students
     */
    async deleteStudentSelectionFieldData(studentSelection: string): Promise<void> {
        await this.page.locator(`input#report-${studentSelection}-selectized`).click();
        await this.page.keyboard.press("Backspace");
    }

    /**
     * toggle the summary inclusion
     */
    async toggleSummaryInclusion(): Promise<void> {
        await this.page.locator(CreateSummativeReport.SUMMARY_TOGGLE).click();
    }

    /**
     * toggle the class inclusion
     */
    async toggleClassInclusion(): Promise<void> {
        await this.page.locator(CreateSummativeReport.CLASS_TOGGLE).click();
    }

    /**
     * fill in the summary heading field
     * @param heading - The heading to fill in
     */
    async fillSummaryHeadingField(heading: string): Promise<void> {
        await this.page.locator(`[data-test="${CreateSummativeReport.DATA_TEST_SUMMARY_HEADING}"]`).fill(heading);
    }

    /**
     * click the show overall grade and/or class code checkbox in the summary section
     * @param showOverallGrade - set true to click the show overall grade checkbox
     * @param showClassCode - set true to click the show class code checkbox
     */
    async tickSummaryOverallGradeClassCodeCheckboxes(showOverallGrade: boolean, showClassCode: boolean): Promise<void> {
        if (showOverallGrade) {
            await this.page.locator(CreateSummativeReport.SUMMARY_OVERALL_GRADE).click();
        }
        if (showClassCode) {
            await this.page.locator(CreateSummativeReport.SUMMARY_CLASS_CODE).click();
        }
    }

    /**
     * click the show overall grade and/or class code checkbox in the class page section
     * @param showOverallGrade - set true to click the show overall grade checkbox
     * @param showClassCode - set true to click the show class code checkbox
     */
    async tickClassOverallGradeClassCodeCheckboxes(showOverallGrade: boolean, showClassCode: boolean): Promise<void> {
        if (showOverallGrade) {
            await this.page.locator(CreateSummativeReport.CLASS_OVERALL_GRADE).click();
        }
        if (showClassCode) {
            await this.page.locator(CreateSummativeReport.CLASS_PAGE_CLASS_CODE).click();
        }
    }

    /**
     * click the show subject overview checkbox in the class page section and type in the title field
     * @param showSubjectOverview - set true to click the show subject overview checkbox
     * @param subjectOverviewTitle - fill in the subject overview title
     */
    async typeSubjectOverview(showSubjectOverview: boolean, subjectOverviewTitle: string): Promise<void> {
        if (showSubjectOverview) {
            await this.page.locator(CreateSummativeReport.CLASS_PAGE_SUBJECT_OVERVIEW).click();
            await this.page.locator("#report-class-search-subject-overview").fill(subjectOverviewTitle);
        }
    }

    /**
     * click the checkboxes for a work type in the summary section according to the options provided without using tickWorkTypeCheckbox method
     * @param workType - the work type to click: assessment, learningTask or behaviour
     * @param options - the options to click: showGrade, showComment, showRubric or showDistributionGraph
     */
    async tickSummaryWorkTypeCheckboxes(workType: string, options: string[]): Promise<void> {
        for (const option of options) {
            const checkboxSelector = CreateSummativeReport.DATA_TEST_SUMMARY_WORK_TYPE_CHECKBOX.replace(
                "{workType}",
                workType,
            ).replace("{option}", option);
            await this.page.getByTestId(checkboxSelector).click();
        }
    }

    /**
     * click the checkboxes for a work type in the class section according to the options provided without using tickWorkTypeCheckbox method
     * @param workType - the work type to click: assessment, learningTask or behaviour
     * @param options - the options to click: showGrade, showComment, showRubric or showDistributionGraph
     */
    async tickClassWorkTypeCheckboxes(workType: string, options: string[]): Promise<void> {
        for (const option of options) {
            const checkboxSelector = CreateSummativeReport.DATA_TEST_CLASS_WORK_TYPE_CHECKBOX.replace(
                "{workType}",
                workType,
            ).replace("{option}", option);
            await this.page.getByTestId(checkboxSelector).click();
        }
    }

    /**
     * toggle the summary curriculum progression checkbox
     */
    async toggleSummaryCurriculumProgression(): Promise<void> {
        await this.page.locator(CreateSummativeReport.ENABLE_SUMMARY_CURRICULUM_PROGRESSION).click();
    }

    /**
     * toggle the class curriculum progression
     */
    async toggleClassCurriculumProgression(): Promise<void> {
        await this.page.locator(CreateSummativeReport.ENABLE_CLASS_CURRICULUM_PROGRESSION).click();
    }

    /**
     * fill in the summary curriculum progression title
     * @param title - The title to fill in
     */
    async fillSummaryCurriculumProgressionTitle(title: string): Promise<void> {
        await this.page.locator(CreateSummativeReport.SUMMARY_CURRICULUM_PROGRESSION_TITLE).fill(title);
    }

    /**
     * fill in the class curriculum progression title
     * @param title - The title to fill in
     */
    async fillClassCurriculumProgressionTitle(title: string): Promise<void> {
        await this.page.locator(CreateSummativeReport.CLASS_CURRICULUM_PROGRESSION_TITLE).fill(title);
    }

    /**
     * check the mandatory field error messages are presented
     */
    async checkMandatoryFieldErrorMessagesArePresented(): Promise<void> {
        await expect(this.page.locator(CreateSummativeReport.TITLE_ERROR)).toBeVisible();
        await expect(this.page.locator(CreateSummativeReport.TITLE_ERROR)).toContainText(
            "Please enter a title (max length 100 characters)",
        );
        await expect(this.page.locator(CreateSummativeReport.PERIODS_ERROR)).toBeVisible();
        await expect(this.page.locator(CreateSummativeReport.PERIODS_ERROR)).toContainText(
            "Please select at least one academic period",
        );
        await expect(this.page.locator(CreateSummativeReport.STUDENTS_ERROR)).toBeVisible();
        await expect(this.page.locator(CreateSummativeReport.STUDENTS_ERROR)).toContainText(
            "Please enter at least one Student Selection",
        );
        await expect(this.page.locator(CreateSummativeReport.SUMMARY_CURRICULUM_PROGRESSION_TITLE_ERROR)).toBeVisible();
        await expect(this.page.locator(CreateSummativeReport.SUMMARY_CURRICULUM_PROGRESSION_TITLE_ERROR)).toContainText(
            "This field is required.",
        );
        await expect(this.page.locator(CreateSummativeReport.CLASS_CURRICULUM_PROGRESSION_TITLE_ERROR)).toBeVisible();
        await expect(this.page.locator(CreateSummativeReport.CLASS_CURRICULUM_PROGRESSION_TITLE_ERROR)).toContainText(
            "This field is required.",
        );
    }
}

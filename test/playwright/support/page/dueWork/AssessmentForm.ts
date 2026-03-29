import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import Common from "@schoolboxTest/playwright/support/page/Common";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";

export default class AssessmentForm {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/folders/{class_id}/assessments/new?type=dueWork";

    static readonly URL_MODIFY = "/learning/assessments/{assessment_id}/modify";

    // Constants for data-test selectors
    static readonly SELECTOR_FORM = "assessment-form";

    static readonly SELECTOR_TITLE = "assessment-form-input-title";

    static readonly SELECTOR_DESCRIPTION = "assessment-form-input-description";

    static readonly SELECTOR_WORKTYPE = "assessment-form-select-workType";

    static readonly DATA_TEST_SELF_REFLECTION = "assessment-form-select-selfReflection";

    static readonly SELECTOR_WEIGHT = "assessment-form-input-weight";

    static readonly SELECTOR_VISIBILITY = "assessment-form-select-visibility";

    static readonly SELECTOR_ACCEPT_SUBMISSION = "assessment-form-select-acceptOnline";

    static readonly SELECTOR_PUBLISH_DATE = "assessment-form-input-date-publish";

    static readonly SELECTOR_OPEN_DATE = "assessment-form-input-date-open";

    static readonly SELECTOR_DUE_DATE = "assessment-form-input-date-due";

    static readonly DATA_TEST_MARK_TYPE = "assessment-form-select-markType";

    static readonly DATA_TEST_INSTRUCTOR_MARK_TYPE = "assessment-form-select-instructor-markType";

    static readonly DATA_TEST_DUAL_MARK_TYPES = "assessment-form-checkbox-dualMarkTypes";

    static readonly SELECTOR_BUTTON_CREATE = "assessment-form-button-submit-Create";

    static readonly SELECTOR_BUTTON_UPDATE = "assessment-form-button-submit-Update";

    static readonly DATA_TEST_SUBMISSION_TYPE_CONTAINER = "assessment-form-submission-type-container";

    static readonly DATA_TEST_SUBMISSION_TYPE_LABEL = "assessment-form-submission-type"; // Append "-[submissionType]"

    static readonly DATA_TEST_USE_RUBRIC = "assessment-form-use-rubric";

    static readonly DATA_TEST_RUBRIC_SPAWN_LEVEL = "rubric-spawn-level";

    static readonly DATA_TEST_RUBRIC_INDICATOR_ROW = "rubric-indicator-row-"; // Append "-[capabilityId]-[indicatorId]"

    static readonly DATA_TEST_RUBRIC_INDICATOR_TOGGLE = "rubric-indicator-toggle";

    static readonly DATA_TEST_RUBRIC_DESCRIPTOR_CELL = "rubric-descriptor-cell-"; // Append "-[descriptorIndex]"

    static readonly DATA_TEST_RUBRIC_DESCRIPTOR_MAX_VALUE = "rubric-descriptor-max-value";

    static readonly SELECTOR_PARTICIPATION_BUTTON_SAVE = "participation-plan-form-button-save";

    static readonly SELECTOR_PARTICIPATION_LEGEND = "participation-plan-legend";

    static readonly SELECTOR_PARTICIPATION_PARTICIPANT = "participation-plan-participant"; // Append "-[userId]"

    static readonly SELECTOR_PARTICIPATION_CUSTOMISE_OPTIONS_POPOUT_BUTTON = "sbx-pop-out-customise-options-button";

    static readonly SELECTOR_PARTICIPATION_CUSTOMISE_OPTIONS_EXCLUDE = "participation-plan-customise-exclude";

    static readonly SELECTOR_PARTICIPATION_CUSTOMISE_OPTIONS_CREATE = "participation-plan-customise-create";

    static readonly SELECTOR_PARTICIPATION_CUSTOMISE_MODAL_PLAN_NAME = "participation-plan-customise-modal-input-name";

    static readonly SELECTOR_PARTICIPATION_CUSTOMISE_MODAL_PLAN_DUE_DATE = `input[id="planDueDate"]`;

    static readonly SELECTOR_CONFIRM_DIALOG_BUTTON_CONFIRM = "confirm-dialog-confirm-button";

    static readonly SELECTOR_CONFIRM_DIALOG_BUTTON_CANCEL = "confirm-dialog-cancel-button";

    static readonly SELECTOR_HOMEPAGE_TABS = "homepage-tabs";

    static readonly DATA_TEST_MODIFY_PLANS = "sbx-pop-out-plan-options-button";

    static readonly SELECTOR_WORKSHEET_ATTACHMENT_ZONE = "#WorksheetZone";

    static readonly DATATEST_MODIFY_PLANS = "sbx-pop-out-plan-options-button";

    // Work type constants
    static readonly WORKTYPE_ASSESSMENT_TASK = 1;

    static readonly WORKTYPE_ASSIGNMENT = 2;

    static readonly WORKTYPE_HOMEWORK = 3;

    static readonly WORKTYPE_NONE = 7;

    // Mark type constants
    static readonly MARK_TYPE_NONE = 6;

    static readonly MARK_TYPE_PERCENTAGE = 1;

    static readonly MARK_TYPE_TOTAL_MARK = 3;

    static readonly MARK_TYPE_LETTER_GRADE = 2;

    // Self reflection constants
    static readonly SELF_REFLECTION_NONE = "none";

    static readonly SELF_REFLECTION_REFLECT_ONLY = "reflect-only";

    static readonly SELF_REFLECTION_FULL = "full";

    // Visibility constants
    // NOTE: The PHP implementation referenced Schoolbox\Assessment\Component\SubmissionBox
    //       but as we are in TypeScript, we do not have access to this class.
    static readonly VISIBILITY_STAFF_ONLY = 0;

    static readonly VISIBILITY_STAFF_AND_STUDENT = 1;

    static readonly VISIBILITY_ALL = 2;

    // Accepts online submission constants
    static readonly ACCEPT_SUBMISSION_NONE = 0;

    static readonly ACCEPT_SUBMISSION_SINGLE = 1;

    static readonly ACCEPT_SUBMISSION_MULTIPLE = 2;

    // Select the type of submission allowed
    static readonly ALLOW_OFFLINE = "allow-offline";

    static readonly TEXT_EDITOR = "text-editor";

    static readonly TYPE_FILES = "files";

    static readonly INTERACTIVE_WORKSHEET = "interactive_worksheet";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to create duework form for a class ID
     * @param classId - The class ID to navigate to
     */
    async goto(classId: string): Promise<void> {
        await this.page.goto(AssessmentForm.URL.replace("{class_id}", classId));
    }

    /**
     * Navigates to the activity modify form for an assessment ID
     * @param assessmentId - The assessment ID to navigate to
     */
    async gotoAssessmentModifyPage(assessmentId: string): Promise<void> {
        await this.page.goto(AssessmentForm.URL_MODIFY.replace("{assessment_id}", assessmentId));
    }

    /**
     * Submits this form to create a duework - by default it skips the participation prompt by clicking Later
     * if you want to navifate to the participation plan page, set toParticipation to true eg: create(true, true);
     * Verifies that the homepage is visible after submission
     * @param hasParticipationPrompt - Whether the participation prompt is expected to be shown (should be when creating a new class/project duework/task)
     * @param toParticipation - Whether to navigate to the participation page
     */
    async create(hasParticipationPrompt: boolean = true, toParticipation: boolean = false): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_BUTTON_CREATE).click();

        if (hasParticipationPrompt && toParticipation) {
            // If the participation prompt is shown, and we wish to navigate to the participation page
            const confirmDialogConfirm = this.page.getByTestId(AssessmentForm.SELECTOR_CONFIRM_DIALOG_BUTTON_CONFIRM);
            await confirmDialogConfirm.click();
        } else if (hasParticipationPrompt && !toParticipation) {
            // Otherwise, if the participation prompt is shown but we wish to skip participation editing
            const confirmDialogCancel = this.page.getByTestId(AssessmentForm.SELECTOR_CONFIRM_DIALOG_BUTTON_CANCEL);
            await confirmDialogCancel.click();
        }

        const homepageTabs = this.page.getByTestId(AssessmentForm.SELECTOR_HOMEPAGE_TABS);
        const courseModifyPage = this.page.locator("h1").filter({ hasText: "Modify Course" });

        // either of the above should be visible
        await expect(homepageTabs.or(courseModifyPage)).toHaveCount(1);
    }

    /**
     * Submits this form to create a duework
     */
    async clickCreate(): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_BUTTON_CREATE).click();
    }

    /**
     * Submits this form to update a duework
     */
    async update(): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_BUTTON_UPDATE).click();
    }

    /**
     * Sets work type
     * @param workType - The work type to set
     */
    async setWorkType(workType: number): Promise<void> {
        const workTypeSelectEl = this.page.getByTestId(AssessmentForm.SELECTOR_WORKTYPE);
        await workTypeSelectEl.selectOption({ value: workType.toString() });
    }

    /**
     * Sets display mark type
     * @param markType - The mark type to set
     */
    async setMarkType(markType: number): Promise<void> {
        const markTypeSelectEl = this.page.getByTestId(AssessmentForm.DATA_TEST_MARK_TYPE);
        await markTypeSelectEl.selectOption({ value: markType.toString() });
    }

    /**
     * Sets instructor mark type
     * @param markType - The mark type to set
     */
    async setInstructorMarkType(markType: number): Promise<void> {
        const markTypeSelectEl = this.page.getByTestId(AssessmentForm.DATA_TEST_INSTRUCTOR_MARK_TYPE);
        await markTypeSelectEl.selectOption({ value: markType.toString() });
    }

    /**
     * Enable the "Use dual mark types" option (and expect that it's not currently checked)
     */
    async enableDualMarkTypes(): Promise<void> {
        const dualMarkTypesSwitch = this.page.getByTestId(AssessmentForm.DATA_TEST_DUAL_MARK_TYPES);
        await expect(dualMarkTypesSwitch).not.toBeChecked();
        await dualMarkTypesSwitch.check();
    }

    /**
     * Disable the "Use dual mark types" option (and expect that it's currently checked)
     */
    async disableDualMarkTypes(): Promise<void> {
        const dualMarkTypesSwitch = this.page.getByTestId(AssessmentForm.DATA_TEST_DUAL_MARK_TYPES);
        await expect(dualMarkTypesSwitch).toBeChecked();
        await dualMarkTypesSwitch.uncheck();
    }

    /**
     * Sets the weight of the assessment
     * @param weight - The weight to set
     */
    async setWeight(weight: number): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_WEIGHT).fill(weight.toString());
    }

    /**
     * Sets work type
     * @param option - The self-reflection option to set
     */
    async setSelfReflection(
        option:
            | typeof AssessmentForm.SELF_REFLECTION_NONE
            | typeof AssessmentForm.SELF_REFLECTION_REFLECT_ONLY
            | typeof AssessmentForm.SELF_REFLECTION_FULL,
    ): Promise<void> {
        const selfReflectionSelectEl = this.page.getByTestId(AssessmentForm.DATA_TEST_SELF_REFLECTION);
        await selfReflectionSelectEl.selectOption({ value: option });
    }

    /**
     * Sets visibility
     * @param visibility - The visibility to set: VISIBILITY_STAFF_ONLY, VISIBILITY_STAFF_AND_STUDENT, VISIBILITY_ALL
     */
    async setVisibility(visibility: string): Promise<void> {
        await this.page.locator(`select[name="visibility"]`).selectOption({ value: visibility });
    }

    /**
     * For each assessment ID, navigates to its modify page and verifies that the expected visibility option is selected.
     * @param assessmentIds - Array of assessment IDs to check.
     * @param visibility -  Array of expected visibility option labels, corresponding to each assessment ID.
     */
    async assessmentModifyCheckVisibilityOptionsSelected(assessmentIds: number[], visibility: string[]): Promise<void> {
        for (let i = 0; i < assessmentIds.length; i += 1) {
            const assessmentId = assessmentIds[i];
            await this.gotoAssessmentModifyPage(assessmentId.toString());
            const visibilitySelectEl = this.page.getByTestId(AssessmentForm.SELECTOR_VISIBILITY);
            const selectedValue = await visibilitySelectEl.locator('option[selected="selected"]').innerText();
            expect(selectedValue).toBe(visibility[i]);
        }
    }

    /**
     * Sets accepts online submissions
     * @param acceptSubmission - The accept submission to set
     */
    async setAcceptSubmission(acceptSubmission: number): Promise<void> {
        const acceptSubmissionSelectEl = this.page.getByTestId(AssessmentForm.SELECTOR_ACCEPT_SUBMISSION);
        await acceptSubmissionSelectEl.selectOption({ value: acceptSubmission.toString() });
    }

    /**
     * Check one of the options to allow submission
     * @param submissionType - The submission type to check
     */
    async checkSubmissionAllowed(
        submissionType:
            | typeof AssessmentForm.INTERACTIVE_WORKSHEET
            | typeof AssessmentForm.ALLOW_OFFLINE
            | typeof AssessmentForm.TEXT_EDITOR
            | typeof AssessmentForm.TYPE_FILES,
    ): Promise<void> {
        const submissionTypeContainer = this.page.getByTestId(AssessmentForm.DATA_TEST_SUBMISSION_TYPE_CONTAINER);
        await expect(submissionTypeContainer).toContainText("Select the type of submission allowed");
        await this.page.getByTestId(`${AssessmentForm.DATA_TEST_SUBMISSION_TYPE_LABEL}-${submissionType}`).click();
    }

    /**
     * Sets title
     * @param title - The title to set
     */
    async setTitle(title: string): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_TITLE).fill(title);
    }

    /**
     * Sets description
     * @param description - The description to set
     */
    async setDescription(description: string): Promise<void> {
        const descriptionTextarea = this.page.getByTestId(AssessmentForm.SELECTOR_DESCRIPTION);
        await this.tester.fillCKEditorByLocator(descriptionTextarea, description);
    }

    /**
     * Sets publish date
     * @param publishDate - The publish date to set
     */
    async setPublishDate(publishDate: Date): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_PUBLISH_DATE).fill(Common.formatDate(publishDate, true));
    }

    /**
     * Sets open date
     * @param openDate - The open date to set
     */
    async setOpenDate(openDate: Date): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_OPEN_DATE).fill(Common.formatDate(openDate, true));
    }

    /**
     * Sets due date
     * @param dueDate - The due date to set
     */
    async setDueDate(dueDate: Date): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_DUE_DATE).fill(Common.formatDate(dueDate, true));
    }

    /**
     * Excludes a user from participants
     * @param userId - The user ID to exclude
     * @param confirm - Whether to confirm the exclusion
     * @param openSection - Whether to open the section
     */
    async modifyParticipation(userId: number, confirm: boolean, openSection: boolean): Promise<void> {
        if (openSection) {
            await this.page.getByTestId(AssessmentForm.SELECTOR_PARTICIPATION_LEGEND).click();
        }
        await this.page.waitForTimeout(2000);
        await this.page.getByTestId(`${AssessmentForm.SELECTOR_PARTICIPATION_PARTICIPANT}-${userId}`).click();
        await this.page.getByTestId(AssessmentForm.SELECTOR_PARTICIPATION_CUSTOMISE_OPTIONS_POPOUT_BUTTON).click();
        await this.page.getByTestId(AssessmentForm.SELECTOR_PARTICIPATION_CUSTOMISE_OPTIONS_EXCLUDE).click();

        if (confirm) {
            await this.page.getByTestId(AssessmentForm.SELECTOR_CONFIRM_DIALOG_BUTTON_CONFIRM).click();
        }
    }

    /**
     * Creates a learning plan
     * @param userId - The user ID to create a learning plan for
     * @param planName - The plan name to set
     * @param date - The date to set
     * @param openSection - Whether to open the section
     */
    async createLearningPlan(userId: number, planName: string, date: string, openSection: boolean): Promise<void> {
        if (openSection) {
            await this.page.getByTestId(AssessmentForm.SELECTOR_PARTICIPATION_LEGEND).click();
        }
        await this.page.waitForTimeout(2000);

        await this.page.getByTestId(`${AssessmentForm.SELECTOR_PARTICIPATION_PARTICIPANT}-${userId}`).click();

        await this.page.getByTestId(AssessmentForm.SELECTOR_PARTICIPATION_CUSTOMISE_OPTIONS_POPOUT_BUTTON).click();
        await this.page.getByTestId(AssessmentForm.SELECTOR_PARTICIPATION_CUSTOMISE_OPTIONS_CREATE).click();

        await this.page.getByTestId(AssessmentForm.SELECTOR_PARTICIPATION_CUSTOMISE_MODAL_PLAN_NAME).fill(planName);
        await this.page.locator(AssessmentForm.SELECTOR_PARTICIPATION_CUSTOMISE_MODAL_PLAN_DUE_DATE).fill(date);
        await this.page.getByTestId(AssessmentForm.SELECTOR_PARTICIPATION_BUTTON_SAVE).click();
    }

    /**
     * Modify a learning plan
     */
    async clickModifyLearningPlan(): Promise<void> {
        await this.page.getByTestId(AssessmentForm.DATA_TEST_MODIFY_PLANS).click();
    }

    /**
     * Sets attachment in the interactive worksheet zone
     * @param db - The DatabaseHelper instance
     * @param filePath - The file to attach
     */
    async worksheetAttachment(db: DatabaseHelper, filePath: string): Promise<void> {
        const attachZone = this.page.locator(AssessmentForm.SELECTOR_WORKSHEET_ATTACHMENT_ZONE);
        await this.tester.dragAndDropFile(db, attachZone, filePath);
    }

    /**
     * Toggles the use rubric option
     */
    async toggleUseRubric(): Promise<void> {
        const useRubricInputId = await this.page.getByTestId(AssessmentForm.DATA_TEST_USE_RUBRIC).getAttribute("id");
        const useRubricLabel = this.page.locator(`.switch label[for="${useRubricInputId}"]`);
        await useRubricLabel.click();
    }

    /**
     * Adds a new rubric indicator level
     */
    async addRubricLevel(): Promise<void> {
        await this.page.getByTestId(AssessmentForm.DATA_TEST_RUBRIC_SPAWN_LEVEL).click();
    }

    /**
     * Toggle a given rubric indicator
     * @param capabilityId - The capability ID
     * @param indicatorId - The indicator ID
     */
    async toggleRubricIndicator(capabilityId: number, indicatorId: number): Promise<void> {
        const indicatorToggle = this.page
            .getByTestId(`${AssessmentForm.DATA_TEST_RUBRIC_INDICATOR_ROW}${capabilityId}-${indicatorId}`)
            .getByTestId(AssessmentForm.DATA_TEST_RUBRIC_INDICATOR_TOGGLE);
        await indicatorToggle.click();
    }

    /**
     * Fill in points for a given descriptor
     * @param capabilityId - The capability ID
     * @param indicatorId - The indicator ID
     * @param descriptorIndex - The index of the descriptor
     * @param value - The value to set
     */
    async fillRubricCriterionPoints(
        capabilityId: number,
        indicatorId: number,
        descriptorIndex: number,
        value: string | number,
    ): Promise<void> {
        const descriptor = this.page
            .getByTestId(`${AssessmentForm.DATA_TEST_RUBRIC_INDICATOR_ROW}${capabilityId}-${indicatorId}`)
            .getByTestId(`${AssessmentForm.DATA_TEST_RUBRIC_DESCRIPTOR_CELL}-${descriptorIndex}`);

        const pointsInput = descriptor.getByTestId(AssessmentForm.DATA_TEST_RUBRIC_DESCRIPTOR_MAX_VALUE);
        await pointsInput.fill(value.toString());
    }
}

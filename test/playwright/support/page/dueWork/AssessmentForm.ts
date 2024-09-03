import { Page, expect } from "@playwright/test";
import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class AssessmentForm {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/folders/{class_id}/assessments/new?type=dueWork";

    // Constants for data-test selectors
    static readonly SELECTOR_FORM = "assessment-form";

    static readonly SELECTOR_TITLE = "assessment-form-input-title";

    static readonly SELECTOR_DESCRIPTION = "assessment-form-input-description";

    static readonly SELECTOR_WORKTYPE = "assessment-form-select-workType";

    static readonly SELECTOR_WEIGHT = "assessment-form-input-weight";

    static readonly SELECTOR_VISIBILITY = "assessment-form-select-visibility";

    static readonly SELECTOR_ACCEPT_SUBMISSION = "assessment-form-select-acceptOnline";

    static readonly SELECTOR_PUBLISH_DATE = "assessment-form-input-date-publish";

    static readonly SELECTOR_OPEN_DATE = "assessment-form-input-date-open";

    static readonly SELECTOR_DUE_DATE = "assessment-form-input-date-due";

    static readonly SELECTOR_MARK_TYPE = "assessment-form-select-markType";

    static readonly SELECTOR_BUTTON_CREATE = "assessment-form-button-submit-Create";

    static readonly SELECTOR_BUTTON_UPDATE = "assessment-form-button-submit-Update";

    static readonly SELECTOR_PARTICIPATION_BUTTON_SAVE = "participation-plan-form-button-save";

    static readonly SELECTOR_PARTICIPATION_LEGEND = "participation-plan-legend";

    static readonly SELECTOR_PARTICIPATION_PARTICIPANT = "participation-plan-participant"; // Append "-[userId]"

    static readonly SELECTOR_PARTICIPATION_CUSTOMISE_OPTIONS_POPOUT_BUTTON = "sbx-pop-out-customise-options-button";

    static readonly SELECTOR_PARTICIPATION_CUSTOMISE_OPTIONS_EXCLUDE = "participation-plan-customise-exclude";

    static readonly SELECTOR_PARTICIPATION_CUSTOMISE_OPTIONS_CREATE = "participation-plan-customise-create";

    static readonly SELECTOR_PARTICIPATION_CUSTOMISE_MODAL_PLAN_NAME = "participation-plan-customise-modal-input-name";

    static readonly SELECTOR_PARTICIPATION_CUSTOMISE_MODAL_PLAN_DUE_DATE =
        "participation-plan-customise-modal-input-due-date";

    static readonly SELECTOR_CONFIRM_DIALOG_BUTTON_CONFIRM = "confirm-dialog-confirm-button";

    static readonly SELECTOR_HOMEPAGE_TABS = "homepage-tabs";

    // Work type constants
    static readonly WORKTYPE_ASSESSMENT_TASK = 1;

    static readonly WORKTYPE_ASSIGNMENT = 2;

    static readonly WORKTYPE_HOMEWORK = 3;

    static readonly WORKTYPE_NONE = 7;

    // Mark type constants
    static readonly MARK_TYPE_NONE = 0;

    static readonly MARK_TYPE_PERCENTAGE = 1;

    static readonly MARK_TYPE_TOTAL_MARK = 3;

    static readonly MARK_TYPE_LETTER_GRADE = 2;

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
     * Submits this form to create a duework
     * Verifies that the homepage is visible after submission
     */
    async create(): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_BUTTON_CREATE).click();
        await expect(this.page.getByTestId(AssessmentForm.SELECTOR_HOMEPAGE_TABS)).toHaveCount(1);
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
     * Sets mark type
     * @param markType - The mark type to set
     */
    async setMarkType(markType: number): Promise<void> {
        const markTypeSelectEl = this.page.getByTestId(AssessmentForm.SELECTOR_MARK_TYPE);
        await markTypeSelectEl.selectOption({ value: markType.toString() });
    }

    /**
     * Sets the weight of the assessment
     * @param weight - The weight to set
     */
    async setWeight(weight: number): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_WEIGHT).fill(weight.toString());
    }

    /**
     * Sets visibility
     * @param visibility - The visibility to set
     */
    async setVisibility(visibility: number): Promise<void> {
        const visibilitySelectEl = this.page.getByTestId(AssessmentForm.SELECTOR_VISIBILITY);
        await visibilitySelectEl.selectOption({ value: visibility.toString() });
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
    async setPublishDate(publishDate: string): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_PUBLISH_DATE).fill(publishDate);
    }

    /**
     * Sets open date
     * @param openDate - The open date to set
     */
    async setOpenDate(openDate: string): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_OPEN_DATE).fill(openDate);
    }

    /**
     * Sets due date
     * @param dueDate - The due date to set
     */
    async setDueDate(dueDate: string): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_DUE_DATE).fill(dueDate);
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
     */
    async createLearningPlan(userId: number, planName: string, date: string): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_PARTICIPATION_LEGEND).click();
        await this.page.getByTestId(`${AssessmentForm.SELECTOR_PARTICIPATION_PARTICIPANT}-${userId}`).click();

        await this.page.getByTestId(AssessmentForm.SELECTOR_PARTICIPATION_CUSTOMISE_OPTIONS_POPOUT_BUTTON).click();
        await this.page.getByTestId(AssessmentForm.SELECTOR_PARTICIPATION_CUSTOMISE_OPTIONS_CREATE).click();

        await this.page.getByTestId(AssessmentForm.SELECTOR_PARTICIPATION_CUSTOMISE_MODAL_PLAN_NAME).fill(planName);
        await this.page.getByTestId(AssessmentForm.SELECTOR_PARTICIPATION_CUSTOMISE_MODAL_PLAN_DUE_DATE).fill(date);

        await this.page.getByTestId(AssessmentForm.SELECTOR_PARTICIPATION_BUTTON_SAVE).click();
    }
}

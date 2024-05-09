/**
 * This test creates due work activities, deletes a due work activity, the student views the activity, submits it in person,
 * the teacher views and marks the submission, the students views the marked submission, the teacher and submits a due work by typing in the ckeditor
 */
import { test, expect } from "@schoolboxTest/playwright/support/fixtures";
import { AssessmentForm } from "@schoolboxTest/playwright/support/page/dueWork/assessmentForm";

// TODO: Move selectors into relevant support page files
const SELECTOR_FOLDER_FORM_INPUT_NAME = "folder-form-input-name";
const SELECTOR_FOLDER_FORM_SELECT_TEMPLATE = "folder-form-select-template";
const SELECTOR_FOLDER_FORM_RADIO_ACCESS_GROUP_READ = "folder-form-radio-access-group-read"; // Append "-[groupID]"
const SELECTOR_FOLDER_FORM_RADIO_ACCESS_GROUP_WRITE = "folder-form-radio-access-group-write"; // Append "-[groupID]"
const SELECTOR_FOLDER_FORM_SUBMIT = "folder-form-submit";

const SELECTOR_RESOURCE_ACTION_HOMEPAGE = "resource-action-homepage";

const SELECTOR_HOMEPAGE_COMPONENT_LOCK_TOGGLE = "homepage-component-lock-toggle";
const SELECTOR_HOMEPAGE_COMPONENT_PALETTE_TAB = "homepage-component-palette-tab"; // Append "-[componentCategoryID]"
const SELECTOR_HOMEPAGE_COMPONENT_PALETTE_ITEM = "homepage-component-palette-item"; // Append "-[componentDefinitionID]"
const SELECTOR_HOMEPAGE_TAB_ACTIVATOR_ADDCOMPONENT = "homepage-tab-activator-addComponent";
const SELECTOR_HOMEPAGE_TAB_CONTROL_POPOUT_BUTTON = "sbx-pop-out-tab-control-button";
const SELECTOR_HOMEPAGE_ACTIONS_FOLDER_MANAGEGROUP = "homepage-actions-folder-manageGroup";

const SELECTOR_COMPONENT_DUEWORK_CREATE_BUTTON = "component-dueWork-create-button";
const SELECTOR_COMPONENT_DUEWORK_EMPTY_CREATE_BUTTON = "component-dueWork-empty-create-button";
const SELECTOR_COMPONENT_DUEWORK_POPOUT_BUTTON = "sbx-pop-out-duework-options"; // Append "[assessmentID]"
const SELECTOR_COMPONENT_ASSESSMENT_TITLE = "component-assessment-title"; // Append "-[assessmentID]"
const SELECTOR_COMPONENT_ASSESSMENT_ACTIONS_DELETE = "component-assessment-actions-delete"; // Append "-[assessmentID]"

const SELECTOR_MANAGEGROUP_CONTAINER = "manage-group-container";
const SELECTOR_MANAGEGROUP_SEARCH_TEXT = "manage-group-search-text";
const SELECTOR_MANAGEGROUP_SEARCH_RESULT = "manage-group-search-result"; // Append "-[userID]"
const SELECTOR_MANAGEGROUP_ADD_MEMBERS_SUBMIT = "manage-group-add-members-submit";
const SELECTOR_MANAGEGROUP_CURRENT_MEMBERS = "manage-group-current-members";

const SELECTOR_ASSESSMENT_SUMMARY_CREATE_BUTTON = "assessment-summary-create-button";

const SELECTOR_CONFIRM_DIALOG_BUTTON_CONFIRM = "confirm-dialog-confirm-button";

// Using .serial as tests rely on prior tests to have been run
test.describe.serial("Initial Due Work functionality tests", () => {
    let homepageUrl: string;
    let folderName: string;

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("Create 2 Assessments", async ({ tester, page }) => {
        const assessmentForm = new AssessmentForm(tester, page);

        const teacher = "Jack Coles";
        const teacherId = 22;
        const student = "Eden Reed";
        const studentId = 6;

        // Simulate creating a homepage and adding members
        folderName = `Test Class Homepage ${Date.now()}`; // Timestamp to ensure uniqueness
        tester.isGoingTo(`create a homepage named "${folderName}"`);
        await page.goto("https://vagrant.dev.schoolbox.cloud/resource/folder/0/create");
        await page.getByTestId(SELECTOR_FOLDER_FORM_SELECT_TEMPLATE).selectOption({ label: "Blank" });
        await page.getByTestId(SELECTOR_FOLDER_FORM_INPUT_NAME).fill(folderName);

        await page.getByTestId(`${SELECTOR_FOLDER_FORM_RADIO_ACCESS_GROUP_WRITE}-1`).check();
        await page.getByTestId(`${SELECTOR_FOLDER_FORM_RADIO_ACCESS_GROUP_WRITE}-2`).check();
        await page.getByTestId(`${SELECTOR_FOLDER_FORM_RADIO_ACCESS_GROUP_READ}-3`).check();
        await page.getByTestId(`${SELECTOR_FOLDER_FORM_RADIO_ACCESS_GROUP_READ}-4`).check();
        await page.getByTestId(`${SELECTOR_FOLDER_FORM_RADIO_ACCESS_GROUP_READ}-5`).check();

        await page.getByTestId(SELECTOR_FOLDER_FORM_SUBMIT).click();

        // Navigate to newly created folder
        tester.isGoingTo("navigate to the newly created folder and store its location for later tests");
        await page.goto("https://vagrant.dev.schoolbox.cloud/resources/");

        const resourceTreeItem = page.getByRole("treeitem", { name: folderName });
        await resourceTreeItem.click();
        await page.getByTestId(SELECTOR_RESOURCE_ACTION_HOMEPAGE).click();

        await expect(page.getByTestId(AssessmentForm.SELECTOR_HOMEPAGE_TABS)).toHaveCount(1);
        homepageUrl = page.url();

        // Add members to the homepage
        tester.isGoingTo("add members to the homepage");
        await page.getByTestId(SELECTOR_HOMEPAGE_TAB_CONTROL_POPOUT_BUTTON).click();
        await page.getByTestId(SELECTOR_HOMEPAGE_ACTIONS_FOLDER_MANAGEGROUP).click();

        await expect(page.getByTestId(SELECTOR_MANAGEGROUP_CONTAINER)).toBeVisible();
        // Add a student
        await page.getByTestId(SELECTOR_MANAGEGROUP_SEARCH_TEXT).fill(student);
        await page.getByTestId(`${SELECTOR_MANAGEGROUP_SEARCH_RESULT}-${studentId}`).click();
        await page.getByTestId(SELECTOR_MANAGEGROUP_ADD_MEMBERS_SUBMIT).click();
        await expect(page.getByTestId(SELECTOR_MANAGEGROUP_ADD_MEMBERS_SUBMIT)).toBeDisabled();

        // Add a teacher
        await page.getByTestId(SELECTOR_MANAGEGROUP_SEARCH_TEXT).fill(teacher);
        await page.getByTestId(`${SELECTOR_MANAGEGROUP_SEARCH_RESULT}-${teacherId}`).click();
        await page.getByTestId(SELECTOR_MANAGEGROUP_ADD_MEMBERS_SUBMIT).click();
        await expect(page.getByTestId(SELECTOR_MANAGEGROUP_ADD_MEMBERS_SUBMIT)).toBeDisabled();

        // Make the teacher an admin
        const currentMembersTable = page.getByTestId(SELECTOR_MANAGEGROUP_CURRENT_MEMBERS);
        const teacherAccessSelect = currentMembersTable.locator("tr.VueTables__row:first-child select");
        await teacherAccessSelect.selectOption({ value: "admin" });

        // Add due work component and create two assessments
        await page.goto(homepageUrl);
        await page.getByTestId(SELECTOR_HOMEPAGE_COMPONENT_LOCK_TOGGLE).check();
        await page.getByTestId(SELECTOR_HOMEPAGE_TAB_ACTIVATOR_ADDCOMPONENT).click();
        await page.getByTestId(`${SELECTOR_HOMEPAGE_COMPONENT_PALETTE_TAB}-3`).click();
        await page.getByTestId(`${SELECTOR_HOMEPAGE_COMPONENT_PALETTE_ITEM}-36`).click();

        const dueWorkComponentLocator = page.locator(
            "[data-component].Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller",
        );
        await expect(dueWorkComponentLocator).toBeVisible();

        // As a teacher, create two due works
        tester.isGoingTo("as a teacher, create two due works");
        await tester.impersonate(teacher);

        await page.goto(homepageUrl);
        await expect(dueWorkComponentLocator).toBeVisible();

        await page.getByTestId(SELECTOR_COMPONENT_DUEWORK_EMPTY_CREATE_BUTTON).click();
        await assessmentForm.setTitle("Test Due Work");
        await assessmentForm.setDescription("We need more fish.");
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.setAcceptSubmission(AssessmentForm.ACCEPT_SUBMISSION_SINGLE);
        await assessmentForm.setWeight(5);
        await assessmentForm.create();

        tester.isGoingTo("expect to see only one assessment on the homepage, named 'Test Due Work'");
        const allAssessmentSelector = new RegExp(`^${SELECTOR_COMPONENT_ASSESSMENT_TITLE}`);
        const assessmentTitlesA = await page.getByTestId(allAssessmentSelector).evaluateAll((elements) => {
            return elements.map((element) => element.textContent);
        });
        expect(assessmentTitlesA).toHaveLength(1);
        expect(assessmentTitlesA).toContain("Test Due Work");

        // Create second due work
        await page.getByTestId(SELECTOR_COMPONENT_DUEWORK_CREATE_BUTTON).click();
        await assessmentForm.setTitle("Test Due Work2");
        await assessmentForm.setDescription("We still need fish.");
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.setAcceptSubmission(AssessmentForm.ACCEPT_SUBMISSION_SINGLE);
        await assessmentForm.setWeight(5);
        await assessmentForm.create();

        tester.isGoingTo("expect to see two assessments on the homepage, with at least one named 'Test Due Work2'");
        const assessmentTitlesB = await page.getByTestId(allAssessmentSelector).evaluateAll((elements) => {
            return elements.map((element) => element.textContent);
        });
        expect(assessmentTitlesB).toHaveLength(2);
        expect(assessmentTitlesB).toContain("Test Due Work2");
    });

    test("Delete an Assessment", async ({ tester, page }) => {
        const teacher = "Jack Coles";

        tester.isGoingTo("as a teacher, delete the 'Test Due Work2' assessment");
        await tester.impersonate(teacher);

        expect(homepageUrl).toBeDefined();
        await page.goto(homepageUrl);

        const allAssessmentSelector = new RegExp(`^${SELECTOR_COMPONENT_ASSESSMENT_TITLE}`);
        const dueWork2Locator = page
            .getByTestId(allAssessmentSelector)
            .locator("[data-assessment-title]", { hasText: "Test Due Work2" });
        await dueWork2Locator.click();

        const currentURL = page.url();
        const assessment2ID = currentURL.split("/").slice(-2, -1)[0];

        await page.goto(homepageUrl);
        await page.getByTestId(`${SELECTOR_COMPONENT_DUEWORK_POPOUT_BUTTON}${assessment2ID}`).click();
        await page.getByTestId(`${SELECTOR_COMPONENT_ASSESSMENT_ACTIONS_DELETE}-${assessment2ID}`).click();
        await page.getByTestId(SELECTOR_CONFIRM_DIALOG_BUTTON_CONFIRM).click();
    });

    test("View Class Work", async ({ tester, page }) => {
        const student = "Eden Reed";

        tester.isGoingTo("as a student, view the 'Test Due Work' assessment");
        await tester.impersonate(student);

        expect(homepageUrl).toBeDefined();
        await page.goto(homepageUrl);

        const allAssessmentSelector = new RegExp(`^${SELECTOR_COMPONENT_ASSESSMENT_TITLE}`);
        const dueWorkLocator = page.getByTestId(allAssessmentSelector).locator("[data-assessment-title]", {
            hasText: "Test Due Work",
        });
        await dueWorkLocator.click();
        await expect(page.getByTestId(SELECTOR_ASSESSMENT_SUMMARY_CREATE_BUTTON)).toBeVisible();
    });

    // TODO: Continue implementing the rest of the tests from codeception

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });
});

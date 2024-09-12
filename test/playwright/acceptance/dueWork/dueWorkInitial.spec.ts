/**
 * This test creates due work activities, deletes a due work activity, the student views the activity, submits it in person,
 * the teacher views and marks the submission, the students views the marked submission, the teacher and submits a due work by typing in the ckeditor
 */
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";

// TODO: Move selectors into relevant support page files
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
const SELECTOR_COMPONENT_ASSESSMENT_ACTIONS_VIEWSTATUS = "component-assessment-actions-viewStatus"; // Append "-[assessmentID]"

const SELECTOR_MANAGEGROUP_CONTAINER = "manage-group-container";
const SELECTOR_MANAGEGROUP_SEARCH_TEXT = "manage-group-search-text";
const SELECTOR_MANAGEGROUP_SEARCH_RESULT = "manage-group-search-result"; // Append "-[userID]"
const SELECTOR_MANAGEGROUP_ADD_MEMBERS_SUBMIT = "manage-group-add-members-submit";
const SELECTOR_MANAGEGROUP_CURRENT_MEMBERS = "manage-group-current-members";

const SELECTOR_ASSESSMENT_STATUS_FEEDBACK_POPOUT_BUTTON = "sbx-pop-out-feedback-options"; // Append "-[userId]"
const SELECTOR_ASSESSMENT_STATUS_FEEDBACK_VIEWSUBMISSION = "assessment-status-feedback-viewSubmission"; // Append "-[userId]"
const SELECTOR_ASSESSMENT_STATUS_META_TITLE = "assessment-status-meta-title";
const SELECTOR_ASSESSMENT_SUMMARY_CREATE_BUTTON = "assessment-summary-create-button";
const SELECTOR_ASSESSMENT_SUBMISSION_CREATE_SUBMITTEDINPERSON = "assessment-submission-create-submittedInPerson";
const SELECTOR_ASSESSMENT_SUBMISSION_CREATE_SUBMIT = "assessment-submission-create-submit";
const SELECTOR_ASSESSMENT_SUBMISSION_HISTORY = "assessment-submission-history";
const SELECTOR_ASSESSMENT_SUBMISSION_INPUT_BODY = "assessment-submission-input-body";
const SELECTOR_ASSESSMENT_RESPONSE_META_TITLE = "assessment-response-meta-title";
const SELECTOR_ASSESSMENT_FEEDBACK_PUBLISH_BUTTON = "assessment-feedback-publish-button";
const SELECTOR_ASSESSMENT_FEEDBACK_COMMENT_TEXTAREA = "assessment-feedback-comment-textarea"; // Append "-[userId]"
const SELECTOR_ASSESSMENT_FEEDBACK_MARK_INPUT = "assessment-feedback-mark-input"; // Append "-[userId]"
const SELECTOR_ASSESSMENT_STUDENT_CAROUSEL_STATUSLABEL = "assessment-student-carousel-statusLabel"; // Append "-[userId]"

const SELECTOR_CONFIRM_DIALOG_BUTTON_CONFIRM = "confirm-dialog-confirm-button";

test.describe("Initial Due Work functionality tests", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });

    let homepageUrl: string;
    let folderName: string;

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("Create 2 Assessments", async ({ tester, page }) => {
        const createFolder = new CreateFolder(page);
        const assessmentForm = new AssessmentForm(tester, page);

        const teacher = "Jack Coles";
        const teacherId = 22;
        const student = "Eden Reed";
        const studentId = 6;

        // Simulate creating a homepage and adding members
        folderName = `Test Class Homepage ${Date.now()}`; // Timestamp to ensure uniqueness
        tester.isGoingTo(`create a homepage named "${folderName}"`);
        await createFolder.goto();
        await createFolder.setHomepageTemplate("Blank");
        await createFolder.typeName(folderName);

        await createFolder.setAccessPermission(1, "write");
        await createFolder.setAccessPermission(2, "write");
        await createFolder.setAccessPermission(3, "read");
        await createFolder.setAccessPermission(4, "read");
        await createFolder.setAccessPermission(5, "read");

        await createFolder.submit();

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
        await assessmentForm.setMarkType(AssessmentForm.MARK_TYPE_PERCENTAGE);
        await assessmentForm.setAcceptSubmission(AssessmentForm.ACCEPT_SUBMISSION_SINGLE);
        await assessmentForm.setWeight(5);
        await assessmentForm.checkSubmissionAllowed(AssessmentForm.ALLOW_OFFLINE);
        await assessmentForm.checkSubmissionAllowed(AssessmentForm.TEXT_EDITOR);
        await assessmentForm.checkSubmissionAllowed(AssessmentForm.TYPE_FILES);
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
        await assessmentForm.checkSubmissionAllowed(AssessmentForm.ALLOW_OFFLINE);
        await assessmentForm.checkSubmissionAllowed(AssessmentForm.TEXT_EDITOR);
        await assessmentForm.checkSubmissionAllowed(AssessmentForm.TYPE_FILES);
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
        const dueWork2Locator = page.getByTestId(allAssessmentSelector).getByText("Test Due Work2");
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
        const dueWorkLocator = page.getByTestId(allAssessmentSelector).getByText("Test Due Work");
        await dueWorkLocator.click();
        await expect(page.getByTestId(SELECTOR_ASSESSMENT_SUMMARY_CREATE_BUTTON)).toBeVisible();
    });

    test("Submit Work In Person By Student", async ({ tester, page }) => {
        const student = "Eden Reed";

        tester.isGoingTo("as a student, submit the 'Test Due Work' assessment in person");
        await tester.impersonate(student);

        expect(homepageUrl).toBeDefined();
        await page.goto(homepageUrl);

        const allAssessmentSelector = new RegExp(`^${SELECTOR_COMPONENT_ASSESSMENT_TITLE}`);
        const dueWorkLocator = page.getByTestId(allAssessmentSelector).getByText("Test Due Work");
        await dueWorkLocator.click();
        await page.getByTestId(SELECTOR_ASSESSMENT_SUMMARY_CREATE_BUTTON).click();

        await page.getByTestId(SELECTOR_ASSESSMENT_SUBMISSION_CREATE_SUBMITTEDINPERSON).check();
        await page.getByTestId(SELECTOR_ASSESSMENT_SUBMISSION_CREATE_SUBMIT).click();
        await expect(page.getByTestId(SELECTOR_ASSESSMENT_SUBMISSION_HISTORY)).toContainText(
            "Submitted in person by Ms Eden Reed",
        );
    });

    test("View Submission", async ({ tester, page }) => {
        const teacher = "Jack Coles";
        const student = "Eden Reed";
        const studentId = 6;

        tester.isGoingTo("as a teacher, view the 'Test Due Work' assessment submission");
        await tester.impersonate(teacher);

        expect(homepageUrl).toBeDefined();
        await page.goto(homepageUrl);

        const allAssessmentSelector = new RegExp(`^${SELECTOR_COMPONENT_ASSESSMENT_TITLE}`);
        const dueWorkLocator = page.getByTestId(allAssessmentSelector).getByText("Test Due Work");
        await dueWorkLocator.click();

        const currentURL = page.url();
        const assessmentID = currentURL.split("/").slice(-2, -1)[0];

        tester.isGoingTo("select 'View Status' of the 'Test Due Work' assessment");
        await page.goto(homepageUrl);
        await page.getByTestId(`${SELECTOR_COMPONENT_DUEWORK_POPOUT_BUTTON}${assessmentID}`).click();
        await page.getByTestId(`${SELECTOR_COMPONENT_ASSESSMENT_ACTIONS_VIEWSTATUS}-${assessmentID}`).click();

        tester.isGoingTo(`navigate to the due work status page for ${student}`);
        await page.getByTestId(`${SELECTOR_ASSESSMENT_STATUS_FEEDBACK_POPOUT_BUTTON}-${studentId}`).click();
        await page.getByTestId(`${SELECTOR_ASSESSMENT_STATUS_FEEDBACK_VIEWSUBMISSION}-${studentId}`).click();
        await expect(page.getByTestId(SELECTOR_ASSESSMENT_RESPONSE_META_TITLE)).toHaveText("Grading Test Due Work");
    });

    test("Mark Submission", async ({ tester, page }) => {
        const teacher = "Jack Coles";
        const student = "Eden Reed";
        const studentId = 6;

        tester.isGoingTo("as a teacher, mark the 'Test Due Work' assessment submission");
        await tester.impersonate(teacher);

        expect(homepageUrl).toBeDefined();
        await page.goto(homepageUrl);

        const allAssessmentSelector = new RegExp(`^${SELECTOR_COMPONENT_ASSESSMENT_TITLE}`);
        const dueWorkLocator = page.getByTestId(allAssessmentSelector).getByText("Test Due Work");
        await dueWorkLocator.click();

        const currentURL = page.url();
        const assessmentID = currentURL.split("/").slice(-2, -1)[0];

        tester.isGoingTo("navigate to the due work status page for Eden Reed");
        await page.goto(homepageUrl);
        await page.getByTestId(`${SELECTOR_COMPONENT_DUEWORK_POPOUT_BUTTON}${assessmentID}`).click();
        await page.getByTestId(`${SELECTOR_COMPONENT_ASSESSMENT_ACTIONS_VIEWSTATUS}-${assessmentID}`).click();

        tester.isGoingTo(`navigate to the due work status page for ${student}`);
        await page.getByTestId(`${SELECTOR_ASSESSMENT_STATUS_FEEDBACK_POPOUT_BUTTON}-${studentId}`).click();
        await page.getByTestId(`${SELECTOR_ASSESSMENT_STATUS_FEEDBACK_VIEWSUBMISSION}-${studentId}`).click();
        await expect(page.getByTestId(SELECTOR_ASSESSMENT_RESPONSE_META_TITLE)).toHaveText("Grading Test Due Work");

        const studentWeAreMarkingUrl = page.url();
        tester.isGoingTo("mark the submission");
        await page
            .getByTestId(`${SELECTOR_ASSESSMENT_FEEDBACK_COMMENT_TEXTAREA}-${studentId}`)
            .fill("Not bad, but you should work on your spelling.");

        await page.getByTestId(`${SELECTOR_ASSESSMENT_FEEDBACK_MARK_INPUT}-${studentId}`).fill("65");

        tester.isGoingTo("publish feedback for the submission and expect to be redirected to the status page");
        await page.getByTestId(SELECTOR_ASSESSMENT_FEEDBACK_PUBLISH_BUTTON).click();
        await expect(page.getByTestId(SELECTOR_ASSESSMENT_STATUS_META_TITLE)).toHaveText("Status of Test Due Work");

        tester.isGoingTo("navigate back to the feedback page");
        await page.goto(studentWeAreMarkingUrl);

        // this if-clause is due to an intermittent issue SA-14676
        if (await page.getByTestId(SELECTOR_ASSESSMENT_FEEDBACK_PUBLISH_BUTTON).isVisible()) {
            tester.isGoingTo("try publish again, as the button is still visible due to an intermittent issue SA-14676");
            tester.isGoingTo("publish feedback for the submission and expect to be redirected to the status page");
            await page.getByTestId(SELECTOR_ASSESSMENT_FEEDBACK_PUBLISH_BUTTON).click();
            await expect(page.getByTestId(SELECTOR_ASSESSMENT_STATUS_META_TITLE)).toHaveText("Status of Test Due Work");

            tester.isGoingTo("navigate back to the feedback page");
            await page.goto(studentWeAreMarkingUrl);
        }

        tester.isGoingTo("check if the assessment status is reviewed");
        await expect(
            page.getByTestId(`${SELECTOR_ASSESSMENT_STUDENT_CAROUSEL_STATUSLABEL}-${studentId}`).first(),
        ).toHaveText("Reviewed");
    });

    test("View Marked Submission", async ({ tester, page }) => {
        const student = "Eden Reed";
        const studentId = 6;

        tester.isGoingTo("as a student, view the marked submission for 'Test Due Work'");
        await tester.impersonate(student);

        await page.goto(`learning/grades/${studentId}`);

        // Click on the grade to view the submission
        await page.locator("a.flex-grade.link-row").filter({ hasText: folderName }).click();

        // Find the relevant assessment
        const testDueWorkRow = page.locator(".assessment").filter({ hasText: "Test Due Work" });
        await testDueWorkRow.scrollIntoViewIfNeeded();

        await expect(testDueWorkRow).toBeVisible();
        await expect(testDueWorkRow.locator(".grade")).toHaveText("65 %");

        await testDueWorkRow.getByText("Test Due Work").click();

        await expect(page.getByText("Feedback by Mr Jack Coles").first()).toBeVisible();
        await expect(page.getByText("Submitted in person by Ms Eden Reed").first()).toBeVisible();
        await expect(page.getByText("65 %").first()).toBeVisible();
    });

    test("Submit Work Typing Something By Teacher", async ({ tester, page }) => {
        const teacher = "Jack Coles";

        tester.isGoingTo("as a teacher, submit a due work");
        await tester.impersonate(teacher);

        expect(homepageUrl).toBeDefined();
        await page.goto(homepageUrl);

        const allAssessmentSelector = new RegExp(`^${SELECTOR_COMPONENT_ASSESSMENT_TITLE}`);
        const dueWorkLocator = page.getByTestId(allAssessmentSelector).getByText("Test Due Work");
        await dueWorkLocator.click();
        await page.getByTestId(SELECTOR_ASSESSMENT_SUMMARY_CREATE_BUTTON).click();

        const submissionInputBody = page.getByTestId(SELECTOR_ASSESSMENT_SUBMISSION_INPUT_BODY);
        await tester.fillCKEditorByLocator(submissionInputBody, "This is a due work submission by typing something!");

        await page.getByTestId(SELECTOR_ASSESSMENT_SUBMISSION_CREATE_SUBMIT).click();

        await expect(page.getByTestId(SELECTOR_ASSESSMENT_SUBMISSION_HISTORY)).toContainText(
            "Submission by Mr Jack Coles",
        );
    });

    test("Submit Work Typing Something By Admin", async ({ tester, page }) => {
        tester.isGoingTo("as an admin, submit a due work");

        expect(homepageUrl).toBeDefined();
        await page.goto(homepageUrl);

        const allAssessmentSelector = new RegExp(`^${SELECTOR_COMPONENT_ASSESSMENT_TITLE}`);
        const dueWorkLocator = page.getByTestId(allAssessmentSelector).getByText("Test Due Work");
        await dueWorkLocator.click();
        await page.getByTestId(SELECTOR_ASSESSMENT_SUMMARY_CREATE_BUTTON).click();

        const submissionInputBody = page.getByTestId(SELECTOR_ASSESSMENT_SUBMISSION_INPUT_BODY);
        await tester.fillCKEditorByLocator(submissionInputBody, "This is a due work submission by typing something!");

        await page.getByTestId(SELECTOR_ASSESSMENT_SUBMISSION_CREATE_SUBMIT).click();

        await expect(page.getByTestId(SELECTOR_ASSESSMENT_SUBMISSION_HISTORY)).toContainText(
            "Submission by Schoolbox Admin",
        );
    });

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });

    test.afterAll(async ({ baseTester, db }) => {
        // Soft-delete data to not interfere with other tests
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does not impact next suite of tests",
        );

        await db.updateInDatabase(
            "folder",
            { deleted_at: "2022-01-15 11:12:34" },
            { "name LIKE": "Test Class Homepage%" },
        );
        await db.updateInDatabase(
            "assessment",
            { deleted_at: "2022-01-15 11:12:34" },
            { "title LIKE": "Test Due Work%" },
        );
    });
});

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";
import Resources from "@schoolboxTest/playwright/support/page/resources/Resources";
import path from "path";
import ManageGroup from "@schoolboxTest/playwright/support/page/group/ManageGroup";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import AssessmentPage from "@schoolboxTest/playwright/support/page/dueWork/AssessmentPage";
import SubmitForm from "@schoolboxTest/playwright/support/page/dueWork/SubmitForm";
import StatusPage from "@schoolboxTest/playwright/support/page/dueWork/StatusPage";

const teacher = "Jack Coles";
const teacherId = 22;
const student = "Jana Reed";

/**
 * covenience function to create a homepage
 * @param tester - The tester
 * @param page - The page
 * @param db - The database helper
 * @param folderName - The folder name
 */
async function createClassPage(
    tester: AcceptanceTester,
    page: Page,
    db: DatabaseHelper,
    folderName: string,
): Promise<void> {
    const createFolder = new CreateFolder(tester, page);
    const resources = new Resources(page);
    const assessmentForm = new AssessmentForm(tester, page);
    const manageGroup = new ManageGroup(page);
    const homepage = new Homepage(tester, page);
    const filename = path.join(__dirname, "../../support/file/baboon.png");

    const homepageTemplateOption = "Blank";
    const schoolCode = "";

    // sets write access permission for Administration and Teacher, read access permission for senior student, parent, junior student and middle student
    await createFolder.createFolder(
        folderName,
        homepageTemplateOption,
        db,
        [
            [1, "write"],
            [2, "write"],
            [3, "read"],
            [4, "read"],
            [5, "read"],
            [6, "read"],
        ],
        schoolCode,
    );

    tester.isGoingTo("Navigate to the homepage");
    await resources.goto();
    const folderId = (await db.grabFromDatabase("folder", "id", { deleted_at: null, name: folderName })) as string;
    expect(folderId).not.toBe(null);
    await resources.clickFolderName(folderName);

    await resources.clickMenuItem("home");
    const homepageUrl = page.url();

    tester.isGoingTo("Add Jack Coles and Jana Reed into the group via manage group page");
    await page.locator("[data-test=sbx-pop-out-tab-control-button]").click();
    await page.getByTestId("homepage-actions-folder-manageGroup").click();
    await tester.common.waitForPageLoad();
    await manageGroup.search(teacher);
    await manageGroup.selectUser(teacherId);
    await manageGroup.clickFromRadioButton("role");
    await manageGroup.clickAddMembers();
    await page.waitForTimeout(1000); // wait to be able to update the db
    tester.isGoingTo("Make Jack Coles the group admin via database");
    await db.updateInDatabase("userAccess", { type: 3 }, { sid: folderId, uid: "22" });
    tester.isGoingTo("Add Jana Reed into the group");
    await manageGroup.clickFromRadioButton("name");
    await manageGroup.search(student);
    await manageGroup.selectUser(25);
    await manageGroup.clickFromRadioButton("role");
    await manageGroup.clickAddMembers();

    tester.isGoingTo("Add the due work component in the homepage");
    await page.goto(homepageUrl);
    await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_LEARNING, "Due Work");

    tester.isGoingTo("Create a due work with Interactive Worksheet");
    await page.getByTestId("component-dueWork-empty-create-button").click();
    await assessmentForm.setTitle("Test Due Work1");
    await assessmentForm.setDescription("We need more fish.");
    await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
    await assessmentForm.setInstructorMarkType(AssessmentForm.MARK_TYPE_PERCENTAGE);
    await assessmentForm.setAcceptSubmission(AssessmentForm.ACCEPT_SUBMISSION_SINGLE);
    await assessmentForm.setWeight(5);
    await assessmentForm.checkSubmissionAllowed(AssessmentForm.INTERACTIVE_WORKSHEET);
    await assessmentForm.worksheetAttachment(db, filename);
    await assessmentForm.create();

    tester.isGoingTo("Create a due work with Submit In Person");
    await assessmentForm.goto(folderId.toString());
    await assessmentForm.setTitle("Test Due Work2");
    await assessmentForm.setDescription("We still need fish.");
    await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
    await assessmentForm.setAcceptSubmission(AssessmentForm.ACCEPT_SUBMISSION_SINGLE);
    await assessmentForm.setWeight(5);
    await assessmentForm.checkSubmissionAllowed(AssessmentForm.ALLOW_OFFLINE);
    await assessmentForm.create();

    tester.isGoingTo("Create a due work with Text Editor");
    await assessmentForm.goto(folderId.toString());
    await assessmentForm.setTitle("Test Due Work3");
    await assessmentForm.setDescription("We still need fish.");
    await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
    await assessmentForm.setAcceptSubmission(AssessmentForm.ACCEPT_SUBMISSION_SINGLE);
    await assessmentForm.setWeight(5);
    await assessmentForm.checkSubmissionAllowed(AssessmentForm.TEXT_EDITOR);
    await assessmentForm.create();

    tester.isGoingTo("Create a due work with File Upload");
    await assessmentForm.goto(folderId.toString());
    await assessmentForm.setTitle("Test Due Work4");
    await assessmentForm.setDescription("We still need fish.");
    await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
    await assessmentForm.setAcceptSubmission(AssessmentForm.ACCEPT_SUBMISSION_SINGLE);
    await assessmentForm.setWeight(5);
    await assessmentForm.checkSubmissionAllowed(AssessmentForm.TYPE_FILES);
    await assessmentForm.create();
}

test.describe("Submission States functionality tests", () => {
    test.describe.configure({ mode: "serial" });

    const folderName = "Test class Homepage";
    let dueWork1Id: number | null = null;
    let dueWork2Id: number | null = null;
    let dueWork3Id: number | null = null;
    let dueWork4Id: number | null = null;

    // Run before each test to ensure login and homepage navigation
    test.beforeEach(async ({ tester }) => {
        await tester.logout();
        await tester.loginAsAdmin();
    });

    // Checking submission states as Student

    test("Due work submission states as Student", async ({ tester, page, db }) => {
        await createClassPage(tester, page, db, folderName);

        dueWork2Id = (await db.grabFromDatabase("assessment", "id", {
            deleted_at: null,
            "title like": "Test Due Work2",
        })) as number;
        dueWork3Id = (await db.grabFromDatabase("assessment", "id", {
            deleted_at: null,
            "title like": "Test Due Work3",
        })) as number;
        dueWork4Id = (await db.grabFromDatabase("assessment", "id", {
            deleted_at: null,
            "title like": "Test Due Work4",
        })) as number;

        const folderId = (await db.grabFromDatabase("folder", "id", { deleted_at: null, name: folderName })) as string;
        const homepage = new Homepage(tester, page);
        const assessmentPage = new AssessmentPage(tester, page);

        await tester.impersonate(student);

        tester.isGoingTo("check the submit In person submission state is available");
        await homepage.goto(parseInt(folderId, 10));
        await homepage.clickAssessmentComponentTitle(dueWork2Id);
        await assessmentPage.clickCreateSubmission();
        await expect(page.getByTestId("assessment-submission-save-draft")).toBeVisible();
        tester.isGoingTo("I have submitted my work in person is visible and editor and file upload are not visible");
        await expect(
            page.getByTestId(SubmitForm.DATA_TEST_ASSESSMENT_SUBMISSION_CREATE_SUBMITTEDINPERSON),
        ).toBeVisible();
        await expect(page.locator("#FilesZone")).not.toBeVisible();
        await expect(page.locator("div.ck.ck-editor").first()).toHaveCount(0);

        tester.isGoingTo("check the Text editor submission state is available");
        await homepage.goto(parseInt(folderId, 10));
        await homepage.clickAssessmentComponentTitle(dueWork3Id);
        await assessmentPage.clickCreateSubmission();
        await expect(page.getByTestId("assessment-submission-save-draft")).toBeVisible();
        tester.isGoingTo("verify Text Editor  is visible and submit in person and upload zone are not presenteed");
        await expect(page.locator("div.ck.ck-editor").first()).toHaveCount(1);
        await expect(
            page.getByTestId(SubmitForm.DATA_TEST_ASSESSMENT_SUBMISSION_CREATE_SUBMITTEDINPERSON),
        ).not.toBeVisible();
        await expect(page.locator("#FilesZone")).not.toBeVisible();

        tester.isGoingTo("check the file upload submission state is available");
        await homepage.goto(parseInt(folderId, 10));
        await homepage.clickAssessmentComponentTitle(dueWork4Id);
        await assessmentPage.clickCreateSubmission();
        await expect(page.getByTestId("assessment-submission-save-draft")).toBeVisible();
        tester.isGoingTo(
            "verify click or drag here to attach is visible  and editor and submit in person are not visible",
        );
        await expect(page.locator("#FilesZone")).toBeVisible();
        await expect(
            page.getByTestId(SubmitForm.DATA_TEST_ASSESSMENT_SUBMISSION_CREATE_SUBMITTEDINPERSON),
        ).not.toBeVisible();
        await expect(page.locator("div.ck.ck-editor").first()).toHaveCount(0);
    });

    // Verifying Class View as teacher

    test("Class View visibility", async ({ tester, db, page }) => {
        dueWork1Id = (await db.grabFromDatabase("assessment", "id", {
            deleted_at: null,
            "title like": "Test Due Work1",
        })) as number;

        const folderId = (await db.grabFromDatabase("folder", "id", { deleted_at: null, name: folderName })) as string;
        const homepage = new Homepage(tester, page);
        const statusPage = new StatusPage(tester, page);

        tester.isGoingTo("as tecaher: checking class view visibility via due work component on homepage");
        await tester.impersonate(teacher);

        await homepage.goto(parseInt(folderId, 10));
        await homepage.clickAssessmentComponentTOTMenu(dueWork1Id);
        await expect(page.locator("#kamiWorksheet")).toBeVisible();

        tester.isGoingTo("check class view is visble on view status page");
        await statusPage.goto(dueWork1Id);
        await statusPage.clickJellyTot();
        await expect(page.locator("#kamiWorksheet")).toBeVisible();
    });

    test.afterAll(async ({ db, api }) => {
        // Soft-delete data to not interfere with other tests
        await api.deleteFoldersByName(db, ["Test Due Work%", "Test Class Homepage%"]);
    });
});

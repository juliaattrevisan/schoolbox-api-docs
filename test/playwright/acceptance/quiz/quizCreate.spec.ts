import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";
import Resources from "@schoolboxTest/playwright/support/page/resources/Resources";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import QuizSettingPage from "@schoolboxTest/playwright/support/page/quiz/QuizSettingPage";
import Common from "@schoolboxTest/playwright/support/page/Common";
import StatusPage from "@schoolboxTest/playwright/support/page/dueWork/StatusPage";
import AssessmentPage from "@schoolboxTest/playwright/support/page/dueWork/AssessmentPage";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";
import ManageGroup from "@schoolboxTest/playwright/support/page/group/ManageGroup";

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
    const createFolder = new CreateFolder(page);
    const resources = new Resources(page);
    const manageGroup = new ManageGroup(page);

    tester.isGoingTo("Create a homepage");
    await createFolder.goto();
    const homepageTemplateOption = "Blank";
    tester.isGoingTo("type a folder name, select a template option and set access permissions");
    await createFolder.typeName(folderName);
    await createFolder.setHomepageTemplate(homepageTemplateOption);
    await createFolder.setAccessPermission(1, "write");
    await createFolder.setAccessPermission(2, "write");
    await createFolder.setAccessPermission(3, "read");
    await createFolder.setAccessPermission(4, "read");
    await createFolder.setAccessPermission(5, "read");
    await createFolder.setAccessPermission(6, "read");
    await createFolder.submit();

    tester.isGoingTo("Navigate to the homepage");
    await resources.goto();
    await expect(page.locator(`li[title="${folderName}"]`)).toBeVisible();
    const folderId = (await db.grabFromDatabase("folder", "id", { deleted_at: null, name: folderName })) as string;
    expect(folderId).not.toBe(null);
    await resources.clickFolderName(folderName);
    await resources.clickMenuItem("home");

    tester.isGoingTo("Add Jana Reed into the group via manage group page");
    await page.locator("[data-test=sbx-pop-out-tab-control-button]").click();
    await page.getByTestId("homepage-actions-folder-manageGroup").click();
    await tester.common.waitForPageLoad();
    await manageGroup.search("Jana Reed");
    await manageGroup.selectUser(25);
    await manageGroup.clickFromRadioButton("role");
    await manageGroup.clickAddMembers();
    await page.waitForTimeout(1000); // wait to be able to update the db
}

test.describe("Quiz Create Tests", () => {
    // Run tests serially, as tests rely on prior tests to have been run.
    test.describe.configure({ mode: "serial" });

    const folderName = "Test Class Homepage Quiz";
    const quizName = "Quiz Automation Test";
    let folderId: string | null = null;
    let quizId: number | null = null;

    test.beforeEach(async ({ tester }) => {
        await tester.logout();
        await tester.loginAsAdmin();
    });

    test("Create a quiz", async ({ tester, page, db }) => {
        const resources = new Resources(page);
        const homepage = new Homepage(page);
        const assessmentForm = new AssessmentForm(tester, page);
        const quiz = new QuizSettingPage(tester, page);

        tester.isGoingTo("create a homepage, navigate to the class page and add a quiz component");
        await createClassPage(tester, page, db, folderName);
        await resources.goto();
        folderId = (await db.grabFromDatabase("folder", "id", { deleted_at: null, name: folderName })) as string;
        await resources.clickFolderName(folderName);
        await resources.clickMenuItem("home");
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_LEARNING, "Quizzes");

        tester.isGoingTo("Create a quiz");
        const layout = Homepage.ELEMENT_COMPONENT_LAYOUT_TOP;
        const position = 1;
        await homepage.clickComponentEmptyStateLink(layout, position);
        await quiz.setTitle(quizName);
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.setMarkType(AssessmentForm.MARK_TYPE_PERCENTAGE);
        await quiz.create(quizName);

        tester.isGoingTo("navigate to the homepage and ensure the quiz is presented");
        await homepage.goto(parseInt(folderId, 10));
        quizId = (await db.grabFromDatabase("assessment", "id", {
            deleted_at: null,
            "title like": quizName,
        })) as number;
        await expect(page.getByTestId(`${Homepage.DATATEST_ASSESSMENT_COMPONENT_TITLE}${quizId}`)).toContainText(
            quizName,
        );
    });

    test("Modify a quiz settings", async ({ tester, page }) => {
        const homepage = new Homepage(page);
        const assessmentForm = new AssessmentForm(tester, page);
        const quiz = new QuizSettingPage(tester, page);

        if (folderId === null || quizId === null) {
            throw new Error("Folder ID or Quiz ID not found");
        }

        tester.isGoingTo("navigate to the class page, click the quiz dropdown and click Modify");
        await homepage.goto(parseInt(folderId, 10));
        await homepage.clickAssessmentComponentTOTMenu(quizId);
        await homepage.clickModifyViaAssessmentComponent("Modify Settings");

        tester.isGoingTo("modify the quiz title and work type");
        await quiz.setTitle("Quiz Automation Test Updated");
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_HOMEWORK);
        await assessmentForm.update();

        tester.isGoingTo("check the quiz title and work type are updated");
        await homepage.clickAssessmentComponentTitle(quizId);

        await expect(page.locator(`ul.breadcrumb`)).toContainText("Quiz Automation Test Updated");
        await expect(page.locator(`li.icon-quiz`)).toHaveText("Homework");
    });

    test("Grading a quiz via Quiz Quick Marking page (Status Page)", async ({ tester, page }) => {
        const assessmentStatusPage = new StatusPage(tester, page);
        const assessmentPage = new AssessmentPage(tester, page);
        const profilePage = new ProfilePage(tester, page);

        if (folderId === null || quizId === null) {
            throw new Error("Folder ID or Quiz ID not found");
        }

        tester.isGoingTo("navigate to the quiz status page, grade a submission and publish the grade");
        await assessmentStatusPage.goto(quizId);
        await assessmentStatusPage.gradeSubmission(25, 80, "Great work!");
        tester.isGoingTo("check the grade is published via attempt history and studennt profile page");
        await assessmentPage.goto(quizId, 25);
        await expect(page.locator("table.attempts-table")).toContainText("80 %");
        await profilePage.goto("25");
        await expect(page.locator("li.feed-item").first()).toContainText("80 %");
        await expect(page.locator("li.feed-item").first()).toContainText("Great work!");
    });

    test("Delete a quiz", async ({ tester, page }) => {
        const homepage = new Homepage(page);
        const common = new Common(page);

        if (folderId === null || quizId === null) {
            throw new Error("Folder ID or Quiz ID not found");
        }

        tester.isGoingTo("navigate to the class page. click the quiz dropdown and click Delete");
        await homepage.goto(parseInt(folderId, 10));
        await homepage.clickAssessmentComponentTOTMenu(quizId);
        await homepage.clickDeleteViaAssessmentComponent(quizId);
        await common.clickConfirmButton();

        tester.isGoingTo("check the quiz is deleted");
        await expect(
            page.locator("div.empty-state").getByText("that is not a part of your course to test your students."),
        ).toBeVisible();
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update delete_at for the quiz assessment so it does not impact the news tests");
        await db.updateInDatabase(
            "assessment",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Quiz Automation Test%", deleted_at: null },
        );
        await db.updateInDatabase(
            "folder",
            { deleted_at: "2020-01-15 11:22:34" },
            { "name like": "Test Class Homepage Quiz" },
        );
    });
});

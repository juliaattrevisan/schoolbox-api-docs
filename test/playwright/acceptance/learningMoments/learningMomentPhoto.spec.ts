/**
 * This test creates, modifies and delete a learning moment, add a comment and delete it
 */
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import LearningMomentsPage from "@schoolboxTest/playwright/support/page/learningMoments/LearningMomentsPage";
import EditorTemplate from "@schoolboxTest/playwright/support/page/admin/design/EditorTemplates";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";

test.describe("Learning Moment Photo Tests", () => {
    /**
     * Deleting data is about to start so it does not get impacted by other tests already executed
     */
    test.beforeAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update delete_at so data does not impact the tests");
        await db.updateInDatabase(
            "moment",
            { deleted_at: "2022-01-15 11:22:34" },
            { "body LIKE": "%his is a learning moment%" },
        );
    });

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("Learning Moment Photo", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const learningMomentsPage = new LearningMomentsPage(tester, page);
        const editorTemplate = new EditorTemplate(page, tester);

        const studentId = 25;

        tester.isGoingTo("Create a editor template to use as learning moment description");
        await editorTemplate.gotoCreateTemplatePage(7);
        await editorTemplate.createTemplate(
            "Learning Moments Automation Template",
            "This is an automation template",
            "This is a learning moments test - take a photo",
        );

        tester.isGoingTo("share a photo learning moment");
        await profilePage.goto(studentId);
        await profilePage.clickShareLearningMoment();
        await learningMomentsPage.clickTakeAPhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();

        tester.isGoingTo("ensure I am able to delete the photo and then take the photo again");
        await learningMomentsPage.deletePhotoEvidence();
        await learningMomentsPage.clickCameraIconButton();
        await learningMomentsPage.clickTakeAPhotoEvidence();

        tester.isGoingTo("write a description");
        await learningMomentsPage.clickWriteSomethingButton();
        await tester.clickEditorTemplateCKEditor("mf-evidence-textarea", "Learning Moments Automation Template");
        await learningMomentsPage.clickSubmit();

        tester.isGoingTo("check if the picture and text are presented in the student activity feed");
        await learningMomentsPage.verifyEvidence("This is a learning moments test - take a photo");
        await learningMomentsPage.verifyEvidenceHasPhoto();
        await profilePage.verifyShareLearningMomentButtonExists();

        tester.isGoingTo("As Jana Reed check that student cannot see the template icon in the CKEditor");
        await tester.impersonate("Jana Reed");
        await profilePage.goto(studentId);
        await profilePage.clickShareLearningMoment();
        await learningMomentsPage.clickWriteSomethingButton();
        await tester.checkTemplateIconVisibility(false);
    });

    test("Learning Moment Comments", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const learningMomentsPage = new LearningMomentsPage(tester, page);
        await profilePage.goto(6);

        tester.isGoingTo("share a learning moment");
        await profilePage.clickShareLearningMoment();
        await learningMomentsPage.clickTakeAPhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();
        await learningMomentsPage.clickWriteSomethingButton();
        await learningMomentsPage.typeMediaEvidenceDescription("This is a learning moments test to be commented");
        await learningMomentsPage.clickSubmit();

        tester.isGoingTo("check the learning moment is presented in the student activity feed and comment it");
        await learningMomentsPage.verifyEvidence("This is a learning moments test to be commented");
        await tester.common.clickAddComment();
        await learningMomentsPage.typeComment();
        await learningMomentsPage.clickPostComment();

        tester.isGoingTo("check the comment is presented");
        await learningMomentsPage.verifyComment("Well done. This is my first comment in this learning moment!");
    });

    test("Learning Moment Modify", async ({ tester, page, db }) => {
        const profilePage = new ProfilePage(tester, page);
        const learningMomentsPage = new LearningMomentsPage(tester, page);
        await profilePage.goto(45);

        tester.isGoingTo("share a learning moment");
        await profilePage.clickShareLearningMoment();
        await learningMomentsPage.clickTakeAPhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();
        await learningMomentsPage.clickWriteSomethingButton();
        await learningMomentsPage.typeMediaEvidenceDescription("This is a learning moments test to be modified");
        await learningMomentsPage.clickSubmit();

        tester.isGoingTo("check the learning moment is presented in the student activity feed and getting it updated");

        await learningMomentsPage.verifyEvidence("This is a learning moments test to be modified");
        const evidenceId = (await db.grabFromDatabase("moment", "id", {
            deleted_at: null,
            "body LIKE": "%This is a learning moments test to be modified%",
            user_id: 45,
        })) as number;
        expect(evidenceId).not.toBe(null);

        await learningMomentsPage.clickDropdownMenu(evidenceId);
        await learningMomentsPage.clickModifySubmenu(evidenceId);
        await learningMomentsPage.typeMediaEvidenceDescription(
            "This is a learning moments test to be modified updated",
        );
        await learningMomentsPage.deletePhotoEvidence();
        await learningMomentsPage.clickCameraIconButton();
        await learningMomentsPage.clickTakeAPhotoEvidence();
        await learningMomentsPage.clickSubmit();

        tester.isGoingTo("check the learning moment is presented in the student activity feed updated");
        await learningMomentsPage.verifyEvidence("This is a learning moments test to be modified updated");
    });

    test("Learning Moment Delete Comment", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const learningMomentsPage = new LearningMomentsPage(tester, page);
        await profilePage.goto(60);

        tester.isGoingTo("share a learning moment");
        await profilePage.clickShareLearningMoment();
        await learningMomentsPage.clickTakeAPhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();
        await learningMomentsPage.clickWriteSomethingButton();
        await learningMomentsPage.typeMediaEvidenceDescription(
            "This is a learning moments test to get comments deleted",
        );
        await learningMomentsPage.clickSubmit();

        tester.isGoingTo("check the learning moment is presented in the student activity feed and adding a comment");
        await learningMomentsPage.verifyEvidence("This is a learning moments test to get comments deleted");
        await tester.common.clickAddComment();
        await learningMomentsPage.typeComment();
        await learningMomentsPage.clickPostComment();

        tester.isGoingTo("check the comment is presented");
        await learningMomentsPage.verifyComment("Well done. This is my first comment in this learning moment!");
        await learningMomentsPage.clickCommentDeleteButton();
        await learningMomentsPage.clickConfirmDeleteButton();

        tester.isGoingTo("check the comment is no longer presented and the count is zero");
        await learningMomentsPage.verifyCommentCount(0);
    });

    test("Learning Moment Delete", async ({ tester, page, db }) => {
        const profilePage = new ProfilePage(tester, page);
        const learningMomentsPage = new LearningMomentsPage(tester, page);
        await profilePage.goto(29);

        tester.isGoingTo("share a learning moment");
        await profilePage.clickShareLearningMoment();
        await learningMomentsPage.clickTakeAPhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();
        await learningMomentsPage.clickWriteSomethingButton();
        await learningMomentsPage.typeMediaEvidenceDescription("This is a learning moments test to get deleted");
        await learningMomentsPage.clickSubmit();

        tester.isGoingTo("check the learning moment is presented in the student activity feed");
        await learningMomentsPage.verifyEvidence("This is a learning moments test to get deleted");

        tester.isGoingTo("delete the learning moment");
        const evidenceId = (await db.grabFromDatabase("moment", "id", {
            deleted_at: null,
            "body LIKE": "%This is a learning moments test to get deleted%",
            user_id: 29,
        })) as number;
        expect(evidenceId).not.toBe(null);

        await learningMomentsPage.clickDropdownMenu(evidenceId);
        await learningMomentsPage.clickLearningMomentDeleteButton(evidenceId);
        await learningMomentsPage.clickLearningMomentConfirmDeleteButton();

        tester.isGoingTo("check the text is no longer presented ");
        await learningMomentsPage.verifyEvidenceNotPresent("This is a learning moment test to get deleted");
        await profilePage.verifyShareLearningMomentButtonExists();
    });

    test.afterAll(async ({ baseTester, db }) => {
        // Soft-delete data to not interfere with other tests
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does impact next suite of tests",
        );
        await db.updateInDatabase(
            "moment",
            { deleted_at: "2022-01-15 11:22:34" },
            { "body LIKE": "%This is a learning moments test - take a photo%", deleted_at: null },
        );
        await db.updateInDatabase(
            "moment",
            { deleted_at: "2022-01-15 11:22:34" },
            { "body LIKE": "%This is a learning moments test to be commented%", deleted_at: null },
        );
        await db.updateInDatabase(
            "moment",
            { deleted_at: "2022-01-15 11:22:34" },
            { "body LIKE": "%This is a learning moments test to be modified%", deleted_at: null },
        );
        await db.updateInDatabase(
            "moment",
            { deleted_at: "2022-01-15 11:22:34" },
            { "body LIKE": "%This is a learning moments test to get comments deleted%", deleted_at: null },
        );
        await db.updateInDatabase(
            "moment",
            { deleted_at: "2022-01-15 11:22:34" },
            { "body LIKE": "%This is a learning moments test to get deleted%", deleted_at: null },
        );
        await db.updateInDatabase(
            "editor_template",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Learning Moments Automation%", deleted_at: null },
        );
    });
});

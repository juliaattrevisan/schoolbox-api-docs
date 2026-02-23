/**
 * This test creates, modifies and delete a learning moment, add a comment and delete it
 */
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import LearningMomentModify from "@schoolboxTest/playwright/support/page/learningMoments/LearningMomentModify";
import EditorTemplate from "@schoolboxTest/playwright/support/page/admin/design/EditorTemplates";
import ActivityFeed from "@schoolboxTest/playwright/support/page/profilePage/ActivityFeed";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";
import EvidenceMediaRecording from "@schoolboxTest/playwright/support/page/learningMoments/EvidenceMediaRecording";

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
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);
        const evidenceMediaRecording = new EvidenceMediaRecording(tester, page);
        const editorTemplate = new EditorTemplate(page, tester);

        const studentId = 25;

        tester.isGoingTo("Create a editor template to use as learning moment description");
        const templateTitle = "Learning Moments Automation Template";
        await editorTemplate.gotoCreateTemplatePage(7);
        await editorTemplate.createTemplate(
            templateTitle,
            "This is an automation template",
            "This is a learning moments test - take a photo",
        );

        tester.isGoingTo("share a photo learning moment");
        await profilePage.goto(studentId);
        await activityFeed.clickShareLearningMoment();
        await activityFeed.shareLearningModal.clickSharePhoto();
        await evidenceMediaRecording.takePhoto();

        tester.isGoingTo("ensure I am able to delete the photo and then take the photo again");
        await learningMomentModify.deleteEvidence();
        await learningMomentModify.share.clickSharePhoto();
        await evidenceMediaRecording.takePhoto();

        tester.isGoingTo("write a description");
        await learningMomentModify.share.clickShareText();
        await learningMomentModify.setBodyEditorTemplate(templateTitle);
        await learningMomentModify.clickPublish();

        tester.isGoingTo("check if the picture and text are presented in the student activity feed");
        await activityFeed.verifyShareLearningMomentButtonExists(); // should have been returned to the activity feed
        await activityFeed.verifyMomentEvidence("This is a learning moments test - take a photo");
        await activityFeed.verifyMomentEvidenceHasPhoto();

        tester.isGoingTo("As Jana Reed check that student cannot see the template icon in the CKEditor");
        await tester.impersonate("Jana Reed");
        await profilePage.goto(studentId);
        await activityFeed.clickShareLearningMoment();
        await learningMomentModify.share.clickShareText();
        await tester.checkTemplateIconVisibility(false);
    });

    test("Learning Moment Comments", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);
        const evidenceMediaRecording = new EvidenceMediaRecording(tester, page);
        await profilePage.goto(6);

        tester.isGoingTo("share a learning moment");
        await activityFeed.clickShareLearningMoment();
        await activityFeed.shareLearningModal.clickSharePhoto();
        await evidenceMediaRecording.takePhoto();

        const momentBody = "This is a learning moments test to be commented";
        await learningMomentModify.share.clickShareText();
        await learningMomentModify.typeBody(momentBody);
        await learningMomentModify.clickPublish();

        tester.isGoingTo("check the learning moment is presented in the student activity feed and comment it");
        await activityFeed.verifyShareLearningMomentButtonExists(); // should have been returned to the activity feed
        await activityFeed.verifyMomentEvidence(momentBody);

        const commentText = "Well done. This is my first comment in this learning moment!";
        await tester.common.clickAddComment();
        await activityFeed.typeMomentComment(commentText);
        await activityFeed.clickMomentPostComment();

        tester.isGoingTo("check the comment is presented");
        await activityFeed.verifyMomentComment(commentText);
    });

    test("Learning Moment Modify", async ({ tester, page, db }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);
        const evidenceMediaRecording = new EvidenceMediaRecording(tester, page);

        const studentId = 45;
        await profilePage.goto(studentId);

        tester.isGoingTo("share a learning moment");
        await activityFeed.clickShareLearningMoment();
        await activityFeed.shareLearningModal.clickSharePhoto();
        await evidenceMediaRecording.takePhoto();

        const momentBody = "This is a learning moments test to be modified";
        await learningMomentModify.share.clickShareText();
        await learningMomentModify.typeBody(momentBody);
        await learningMomentModify.clickPublish();

        tester.isGoingTo("check the learning moment is presented in the student activity feed and getting it updated");
        await activityFeed.verifyShareLearningMomentButtonExists(); // should have been returned to the activity feed
        await activityFeed.verifyMomentEvidence(momentBody);
        const evidenceId = (await db.grabFromDatabase("moment", "id", {
            deleted_at: null,
            "body LIKE": `%${momentBody}%`,
            user_id: studentId,
        })) as number;
        expect(evidenceId).not.toBe(null);

        await activityFeed.clickMomentDropdownMenu(evidenceId);
        await activityFeed.clickMomentModifySubmenu(evidenceId);

        const updatedMomentBody = "This is a learning moments test to be modified updated";
        await learningMomentModify.typeBody(updatedMomentBody);
        await learningMomentModify.deleteEvidence();
        await learningMomentModify.share.clickSharePhoto();
        await evidenceMediaRecording.takePhoto();
        await learningMomentModify.clickPublish();

        tester.isGoingTo("check the learning moment is presented in the student activity feed updated");
        await activityFeed.verifyShareLearningMomentButtonExists(); // should have been returned to the activity feed
        await activityFeed.verifyMomentEvidence(updatedMomentBody);
    });

    test("Learning Moment Delete Comment", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);
        const evidenceMediaRecording = new EvidenceMediaRecording(tester, page);
        await profilePage.goto(60);

        tester.isGoingTo("share a learning moment");
        await activityFeed.clickShareLearningMoment();
        await activityFeed.shareLearningModal.clickSharePhoto();
        await evidenceMediaRecording.takePhoto();

        const momentBody = "This is a learning moments test to get comments deleted";
        await learningMomentModify.share.clickShareText();
        await learningMomentModify.typeBody(momentBody);
        await learningMomentModify.clickPublish();

        tester.isGoingTo("check the learning moment is presented in the student activity feed and adding a comment");
        await activityFeed.verifyShareLearningMomentButtonExists(); // should have been returned to the activity feed
        await activityFeed.verifyMomentEvidence(momentBody);

        const commentText = "Well done. This is my first comment in this learning moment!";
        await tester.common.clickAddComment();
        await activityFeed.typeMomentComment(commentText);
        await activityFeed.clickMomentPostComment();

        tester.isGoingTo("check the comment is presented");
        await activityFeed.verifyMomentComment(commentText);
        await activityFeed.clickMomentCommentDeleteButton();
        await activityFeed.clickMomentConfirmCommentDeleteButton();

        tester.isGoingTo("check the comment is no longer presented and the count is zero");
        await activityFeed.verifyMomentCommentCount(0);
    });

    test("Learning Moment Delete", async ({ tester, page, db }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);
        const evidenceMediaRecording = new EvidenceMediaRecording(tester, page);

        const studentId = 29;
        await profilePage.goto(studentId);

        tester.isGoingTo("share a learning moment");
        await activityFeed.clickShareLearningMoment();
        await activityFeed.shareLearningModal.clickSharePhoto();
        await evidenceMediaRecording.takePhoto();

        const momentBody = "This is a learning moments test to get deleted";
        await learningMomentModify.share.clickShareText();
        await learningMomentModify.typeBody(momentBody);
        await learningMomentModify.clickPublish();

        tester.isGoingTo("check the learning moment is presented in the student activity feed");
        await activityFeed.verifyShareLearningMomentButtonExists(); // should have been returned to the activity feed
        await activityFeed.verifyMomentEvidence(momentBody);

        tester.isGoingTo("delete the learning moment");
        const evidenceId = (await db.grabFromDatabase("moment", "id", {
            deleted_at: null,
            "body LIKE": `%${momentBody}%`,
            user_id: studentId,
        })) as number;
        expect(evidenceId).not.toBe(null);

        await activityFeed.clickMomentDropdownMenu(evidenceId);
        await activityFeed.clickMomentDeleteButton(evidenceId);
        await activityFeed.clickMomentConfirmDeleteButton();

        tester.isGoingTo("check the text is no longer presented ");
        await activityFeed.verifyShareLearningMomentButtonExists(); // should have been returned to the activity feed
        await activityFeed.verifyMomentCount(0);
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

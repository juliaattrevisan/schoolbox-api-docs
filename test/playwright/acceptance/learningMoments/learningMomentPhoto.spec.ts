/**
 * This test creates, modifies and delete a learning moment, add a comment and delete it
 */
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import LearningMomentsPage from "@schoolboxTest/playwright/support/page/learningMoments/LearningMomentsPage";

test.describe("Learning Moment Photo Tests", () => {
    /**
     * Deleting data is about to start so it does not get impacted by other tests already executed
     */
    test.beforeAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update delete_at so data does not impact the tests");
        await db.updateInDatabase(
            "evidence",
            { deleted_at: "2022-01-15 11:22:34" },
            { "body LIKE": "%his is a learning moment%" },
        );
    });

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("Learning Moment Photo", async ({ tester, page }) => {
        const learningMomentsPage = new LearningMomentsPage(tester, page);
        await page.goto(LearningMomentsPage.getStudentProfilePage(25));

        tester.isGoingTo("share a photo learning moment");
        await learningMomentsPage.clickShareLearningMomentLink();
        await learningMomentsPage.clickTakeAPhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();

        tester.isGoingTo("ensure I am able to delete the photo and then take the photo again");
        await learningMomentsPage.deletePhotoEvidence();
        await learningMomentsPage.confirmDeletePhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();

        tester.isGoingTo("write a description");
        await learningMomentsPage.typeMediaEvidenceDescription("This is a learning moments test - take a photo");
        await learningMomentsPage.clickSubmit();

        tester.isGoingTo("check if the picture and text are presented in the student activity feed");
        await learningMomentsPage.verifyEvidence("This is a learning moments test - take a photo");
        await learningMomentsPage.verifyEvidenceHasPhoto();
        await learningMomentsPage.verifyShareLearningMomentLinkExists();
    });

    test("Learning Moment Comments", async ({ tester, page }) => {
        const learningMomentsPage = new LearningMomentsPage(tester, page);
        await page.goto(LearningMomentsPage.getStudentProfilePage(6));

        tester.isGoingTo("share a learning moment");
        await learningMomentsPage.clickShareLearningMomentLink();
        await learningMomentsPage.clickTakeAPhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();
        await learningMomentsPage.deletePhotoEvidence();
        await learningMomentsPage.confirmDeletePhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();
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
        const learningMomentsPage = new LearningMomentsPage(tester, page);
        await page.goto(LearningMomentsPage.getStudentProfilePage(45));

        tester.isGoingTo("share a learning moment");
        await learningMomentsPage.clickShareLearningMomentLink();
        await learningMomentsPage.clickTakeAPhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();
        await learningMomentsPage.deletePhotoEvidence();
        await learningMomentsPage.confirmDeletePhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();
        await learningMomentsPage.typeMediaEvidenceDescription("This is a learning moments test to be modified");
        await learningMomentsPage.clickSubmit();

        tester.isGoingTo("check the learning moment is presented in the student activity feed and getting it updated");

        await learningMomentsPage.verifyEvidence("This is a learning moments test to be modified");
        const evidenceId = (await db.grabFromDatabase("evidence", "id", {
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
        await learningMomentsPage.confirmDeletePhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();
        await learningMomentsPage.clickSubmit();

        tester.isGoingTo("check the learning moment is presented in the student activity feed updated");
        await learningMomentsPage.verifyEvidence("This is a learning moments test to be modified updated");
    });

    test("Learning Moment Delete Comment", async ({ tester, page }) => {
        const learningMomentsPage = new LearningMomentsPage(tester, page);
        await page.goto(LearningMomentsPage.getStudentProfilePage(60));

        tester.isGoingTo("share a learning moment");
        await learningMomentsPage.clickShareLearningMomentLink();
        await learningMomentsPage.clickTakeAPhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();
        await learningMomentsPage.deletePhotoEvidence();
        await learningMomentsPage.confirmDeletePhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();
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
        const learningMomentsPage = new LearningMomentsPage(tester, page);
        await page.goto(LearningMomentsPage.getStudentProfilePage(29));

        tester.isGoingTo("share a learning moment");
        await learningMomentsPage.clickShareLearningMomentLink();
        await learningMomentsPage.clickTakeAPhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();
        await learningMomentsPage.deletePhotoEvidence();
        await learningMomentsPage.confirmDeletePhoto();
        await learningMomentsPage.clickTakeAPhotoEvidence();
        await learningMomentsPage.typeMediaEvidenceDescription("This is a learning moments test to get deleted");
        await learningMomentsPage.clickSubmit();

        tester.isGoingTo("check the learning moment is presented in the student activity feed");
        await learningMomentsPage.verifyEvidence("This is a learning moments test to get deleted");

        tester.isGoingTo("delete the learning moment");
        const evidenceId = (await db.grabFromDatabase("evidence", "id", {
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
        await learningMomentsPage.verifyShareLearningMomentLinkExists();
    });

    test.afterAll(async ({ baseTester, db }) => {
        // Soft-delete data to not interfere with other tests
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does impact next suite of tests",
        );
        await db.updateInDatabase(
            "evidence",
            { deleted_at: "2022-01-15 11:22:34" },
            { "body LIKE": "%This is a learning moments test - take a photo%" },
        );
        await db.updateInDatabase(
            "evidence",
            { deleted_at: "2022-01-15 11:22:34" },
            { "body LIKE": "%This is a learning moments test to be commented%" },
        );
        await db.updateInDatabase(
            "evidence",
            { deleted_at: "2022-01-15 11:22:34" },
            { "body LIKE": "%This is a learning moments test to be modified%" },
        );
        await db.updateInDatabase(
            "evidence",
            { deleted_at: "2022-01-15 11:22:34" },
            { "body LIKE": "%This is a learning moments test to get comments deleted%" },
        );
        await db.updateInDatabase(
            "evidence",
            { deleted_at: "2022-01-15 11:22:34" },
            { "body LIKE": "%This is a learning moments test to get deleted%" },
        );
    });
});

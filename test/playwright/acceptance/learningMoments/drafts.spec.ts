import { EvidenceStatus } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import { test } from "@schoolboxTest/playwright/support/integrations";
import LearningMomentModify from "@schoolboxTest/playwright/support/page/learningMoments/LearningMomentModify";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";
import Common from "@schoolboxTest/playwright/support/page/Common";
import ActivityFeed from "@schoolboxTest/playwright/support/page/profilePage/ActivityFeed";

const MOMENT_BODY_PREFIX = "This is a learning moment test "; // Distinct prefix used to find learning moments to soft-delete at the end of the test

test.describe("Learning Moment Drafts", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("draft created by student, visible/modifiable/publishable by creator", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);

        const studentId = Common.USER_ID_LOUISASMITH;
        const studentName = Common.USER_LOUISASMITH;

        tester.isGoingTo("As Luisa Smith, save a draft learning moment");
        await tester.impersonateViaUserProfilePage(studentName);
        await profilePage.goto(studentId);
        await activityFeed.clickShareLearningMoment();
        await activityFeed.shareLearningModal.clickShareText();

        const momentBody = `${MOMENT_BODY_PREFIX} draft by student`;
        await learningMomentModify.typeBody(momentBody);
        await learningMomentModify.clickSaveDraft();

        // Draft created, should be sent back to the activity feed

        tester.isGoingTo("check the learning moment has draft status and is visible to the creator");
        const moment = activityFeed.locateMomentWithBody(momentBody);
        const momentId = await ActivityFeed.getMomentLocatorId(moment);
        await ActivityFeed.verifyMomentHasStatus(moment, EvidenceStatus.DRAFT);

        tester.isGoingTo("modify the draft learning moment as the creator");
        await ActivityFeed.clickMomentModifyAction(moment);
        const modifiedMomentBody = `${momentBody} - modified`;
        await learningMomentModify.typeBody(modifiedMomentBody);
        await learningMomentModify.clickSaveDraft();

        // After modifying, should be sent back to the activity feed

        tester.isGoingTo("check the modified learning moment is visible and has draft status");
        const modifiedMoment = activityFeed.locateMomentWithBody(modifiedMomentBody);
        await ActivityFeed.verifyMomentHasStatus(modifiedMoment, EvidenceStatus.DRAFT);

        tester.isGoingTo("publish the learning moment as the creator");
        await ActivityFeed.clickMomentPublishAction(modifiedMoment);

        // After publishing, activity feed should update accordingly

        tester.isGoingTo("check the learning moment is published");
        const publishedMoment = activityFeed.locateMomentWithBody(modifiedMomentBody);
        await ActivityFeed.verifyMomentHasStatus(publishedMoment, EvidenceStatus.PUBLISHED);

        // Modify again from a published state via the 3-dot menu, should go back to draft
        tester.isGoingTo(
            "modify the published learning moment as the creator, should go back to draft without changing the content",
        );
        await activityFeed.clickMomentDropdownMenu(momentId);
        await activityFeed.clickMomentModifySubmenu(momentId);
        await learningMomentModify.clickSaveDraft();

        await ActivityFeed.verifyMomentHasStatus(modifiedMoment, EvidenceStatus.DRAFT);

        // Now publish via the modify screen (moderation is off)
        tester.isGoingTo("publish the learning moment from the modify screen");
        await ActivityFeed.clickMomentModifyAction(modifiedMoment);
        await learningMomentModify.clickPublish();
    });

    /**
     * Restore original state / soft-delete data as to not interfere with other tests
     */
    test.afterAll(async ({ db, api }) => {
        const momentIds = (await db.grabColumnFromDatabase("moment", "id", {
            "body LIKE": `%${MOMENT_BODY_PREFIX}%`,
            deleted_at: null,
        })) as number[];
        await Promise.all(momentIds.map((momentId) => api.deleteLearningMoment(momentId)));
    });
});

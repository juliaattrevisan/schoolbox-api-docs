import { test } from "@schoolboxTest/playwright/support/integrations";
import LearningMomentModify from "@schoolboxTest/playwright/support/page/learningMoments/LearningMomentModify";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";
import Common from "@schoolboxTest/playwright/support/page/Common";
import ActivityFeed from "@schoolboxTest/playwright/support/page/profilePage/ActivityFeed";
import { EvidenceStatus } from "@schoolboxLearningMomentEvidence/evidenceTypes";

const CONFIG_LEARNING_MOMENTS_MODERATION_ENABLED = "learning_moments_moderation_enabled";
const YEAR_LEVEL_NAME_ON = "9"; // The name of the year level to enable moderation for
const MOMENT_BODY_PREFIX = "This is a learning moment test "; // Distinct prefix used to find learning moments to soft-delete at the end of the test

test.describe("Learning Moment Moderation", () => {
    /**
     * Preparations
     * - Enable the Learning Moments Moderation module
     * - Enable Learning Moments Moderation for Year 9 only
     */
    test.beforeAll(async ({ db }) => {
        await db.updateInDatabase("config", { value: 1 }, { name: CONFIG_LEARNING_MOMENTS_MODERATION_ENABLED });
        await db.updateInDatabase("lu_year_level", { learning_moments_moderation: 1 }, { name: YEAR_LEVEL_NAME_ON });
    });

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("As a student, draft a learning moment for a moderated year level", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);

        const studentId = Common.USER_ID_JANAREED;
        const studentName = Common.USER_JANAREED;

        tester.isGoingTo("As a student, save a draft learning moment");
        await tester.impersonateViaUserProfilePage(studentName);
        await profilePage.goto(studentId);
        await activityFeed.clickShareLearningMoment();
        await activityFeed.shareLearningModal.clickShareText();

        const momentBody = `${MOMENT_BODY_PREFIX} draft`;
        await learningMomentModify.typeBody(momentBody);
        await learningMomentModify.clickSaveDraft();

        tester.isGoingTo("check the learning moment is saved as a draft");
        const moment = activityFeed.locateMomentWithBody(momentBody);
        await ActivityFeed.verifyMomentHasStatus(moment, EvidenceStatus.DRAFT);

        // From the activity feed, you should be able to submit the draft for approval directly
        tester.isGoingTo("submit the learning moment for approval via the activity feed");
        await ActivityFeed.clickMomentSubmitForApprovalAction(moment);

        tester.isGoingTo("check the learning moment requires approval");
        await ActivityFeed.verifyMomentHasStatus(moment, EvidenceStatus.SUBMITTED);
    });

    test("As a student, submit a learning moment for approval for a moderated year level", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);

        const studentId = Common.USER_ID_JANAREED;
        const studentName = Common.USER_JANAREED;

        tester.isGoingTo("As a student, submit a learning moment for approval");
        await tester.impersonateViaUserProfilePage(studentName);
        await profilePage.goto(studentId);
        await activityFeed.clickShareLearningMoment();
        await activityFeed.shareLearningModal.clickShareText();

        const momentBody = `${MOMENT_BODY_PREFIX} that needs approval`;
        await learningMomentModify.typeBody(momentBody);
        await learningMomentModify.clickSubmitForApproval();

        tester.isGoingTo("check the learning moment requires approval");
        const moment = activityFeed.locateMomentWithBody(momentBody);
        await ActivityFeed.verifyMomentHasStatus(moment, EvidenceStatus.SUBMITTED);

        // From the activity feed, you should be able to modify the learning moment that currently requires approval
        tester.isGoingTo("modify the learning moment, which should put it back to draft");
        await ActivityFeed.clickMomentModifyAction(moment);
        const modifiedMomentBody = `${momentBody} - modified`;
        await learningMomentModify.typeBody(modifiedMomentBody);
        await learningMomentModify.clickSubmitForApproval();

        tester.isGoingTo("check the modified learning moment still requires approval");
        const modifiedMoment = activityFeed.locateMomentWithBody(modifiedMomentBody);
        await ActivityFeed.verifyMomentHasStatus(modifiedMoment, EvidenceStatus.SUBMITTED);
    });

    test("As a teacher, approve a learning moment", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);

        const studentId = Common.USER_ID_JANAREED;
        const studentName = Common.USER_JANAREED;

        tester.isGoingTo("As a student, submit a learning moment for approval");
        await tester.impersonateViaUserProfilePage(studentName);
        await profilePage.goto(studentId);
        await activityFeed.clickShareLearningMoment();
        await activityFeed.shareLearningModal.clickShareText();

        const momentBody = `${MOMENT_BODY_PREFIX} that needs approval`;
        await learningMomentModify.typeBody(momentBody);
        await learningMomentModify.clickSubmitForApproval();

        tester.isGoingTo("check the learning moment requires approval");
        const moment = activityFeed.locateMomentWithBody(momentBody);
        await ActivityFeed.verifyMomentHasStatus(moment, EvidenceStatus.SUBMITTED);

        // Switch to a teacher (Jack Coles) who has access to Year 9 English
        tester.isGoingTo("Impersonate a teacher and navigate to the student's profile page");
        await tester.impersonateTeacher();
        await profilePage.goto(studentId);

        tester.isGoingTo("check the learning moment requires approval");
        await ActivityFeed.verifyMomentHasStatus(moment, EvidenceStatus.SUBMITTED);

        // From the activity feed, you should be able to approve (publish) the learning moment directly
        tester.isGoingTo("approve the learning moment via the activity feed");
        await ActivityFeed.clickMomentPublishAction(moment);

        tester.isGoingTo("check the learning moment is published");
        const publishedMoment = activityFeed.locateMomentWithBody(momentBody);
        await ActivityFeed.verifyMomentHasStatus(publishedMoment, EvidenceStatus.PUBLISHED);
    });

    test("As a teacher, reject a learning moment", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);

        const studentId = Common.USER_ID_JANAREED;
        const studentName = Common.USER_JANAREED;

        tester.isGoingTo("As a student, submit a learning moment for approval");
        await tester.impersonateViaUserProfilePage(studentName);
        await profilePage.goto(studentId);
        await activityFeed.clickShareLearningMoment();
        await activityFeed.shareLearningModal.clickShareText();

        const momentBody = `${MOMENT_BODY_PREFIX} that needs approval`;
        await learningMomentModify.typeBody(momentBody);
        await learningMomentModify.clickSubmitForApproval();

        tester.isGoingTo("check the learning moment requires approval");
        const moment = activityFeed.locateMomentWithBody(momentBody);
        await ActivityFeed.verifyMomentHasStatus(moment, EvidenceStatus.SUBMITTED);

        // Switch to a teacher (Jack Coles) who has access to Year 9 English
        tester.isGoingTo("Impersonate a teacher and navigate to the student's profile page");
        await tester.impersonateTeacher();
        await profilePage.goto(studentId);

        tester.isGoingTo("check the learning moment requires approval");
        await ActivityFeed.verifyMomentHasStatus(moment, EvidenceStatus.SUBMITTED);

        // From the activity feed, you should be able to approve (publish) the learning moment directly
        tester.isGoingTo("reject the learning moment via the activity feed");
        const requestUpdateReason = "reason for requesting an update";
        await ActivityFeed.clickMomentModifyAction(moment);
        await learningMomentModify.clickRequestUpdate();
        await learningMomentModify.confirmRequestUpdate(requestUpdateReason);

        tester.isGoingTo("check the learning moment is rejected and has the right comment attached");
        await ActivityFeed.verifyMomentHasStatus(moment, EvidenceStatus.REJECTED);
        await activityFeed.verifyMomentComment(requestUpdateReason);

        tester.isGoingTo("approve the rejected learning moment via the activity feed");
        await ActivityFeed.clickMomentPublishAction(moment);

        tester.isGoingTo("check the learning moment is published");
        const publishedMoment = activityFeed.locateMomentWithBody(momentBody);
        await ActivityFeed.verifyMomentHasStatus(publishedMoment, EvidenceStatus.PUBLISHED);
    });

    /**
     * (per test) soft-delete all learning moments that were created as to not interfere with other tests
     */
    test.afterEach(async ({ db, api }) => {
        const momentIds = (await db.grabColumnFromDatabase("moment", "id", {
            "body LIKE": `%${MOMENT_BODY_PREFIX}%`,
        })) as number[];
        await Promise.all(momentIds.map((momentId) => api.deleteLearningMoment(momentId)));
    });

    /**
     * Restore original settings as to not interfere with other tests
     */
    test.afterAll(async ({ db }) => {
        await db.updateInDatabase("config", { value: 0 }, { name: CONFIG_LEARNING_MOMENTS_MODERATION_ENABLED });
        await db.updateInDatabase("lu_year_level", { learning_moments_moderation: 0 }, { name: YEAR_LEVEL_NAME_ON });
    });
});

import { expect, test } from "@schoolboxTest/playwright/support/integrations";
import LearningMomentModify from "@schoolboxTest/playwright/support/page/learningMoments/LearningMomentModify";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";
import Common from "@schoolboxTest/playwright/support/page/Common";
import ActivityFeed from "@schoolboxTest/playwright/support/page/profilePage/ActivityFeed";
import { EvidenceStatus } from "@schoolboxLearningMomentEvidence/evidenceTypes";

const CONFIG_LEARNING_MOMENTS_MODERATION_ENABLED = "learning_moments_moderation_enabled";
const YEAR_LEVEL_NAME_ON = "9"; // The name of the year level to enable moderation for
const MOMENT_BODY_PREFIX = "This is a learning moment test "; // Distinct prefix used to find learning moments to soft-delete at the end of the test

// The student which is part of the moderated year level to use between tests
const STUDENT_ID = Common.USER_ID_JANAREED;
const STUDENT_NAME = Common.USER_JANAREED_FULL;

test.describe("Learning Moment Moderation", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });

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

    let moment1Body = `${MOMENT_BODY_PREFIX} 1 - draft created by student`;
    const moment2Body = `${MOMENT_BODY_PREFIX} 2 - draft created by student, submitted for approval`;
    const moment3Body = `${MOMENT_BODY_PREFIX} 3 - submitted for approval by student`;

    const requestUpdateReason = "reason for requesting an update";

    test("As a student, create 2 draft learning moments", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);

        await tester.impersonateViaUserProfilePage(STUDENT_NAME);
        await profilePage.goto(STUDENT_ID);

        tester.isGoingTo("As a student, create the 1st draft learning moment");
        await activityFeed.clickShareLearningMoment();
        await activityFeed.shareLearningModal.clickShareText();
        await learningMomentModify.typeBody(moment1Body);
        await learningMomentModify.clickSaveDraft();

        tester.isGoingTo("As a student, create the 2nd draft learning moment");
        await activityFeed.clickShareLearningMoment();
        await activityFeed.shareLearningModal.clickShareText();
        await learningMomentModify.typeBody(moment2Body);
        await learningMomentModify.clickSaveDraft();

        tester.isGoingTo("check both learning moments are visible and saved as drafts");
        const moment1 = activityFeed.locateMomentWithBody(moment1Body);
        const moment2 = activityFeed.locateMomentWithBody(moment2Body);
        await ActivityFeed.verifyMomentHasStatus(moment1, EvidenceStatus.DRAFT);
        await ActivityFeed.verifyMomentHasStatus(moment2, EvidenceStatus.DRAFT);
    });

    test("As a student, modify a draft learning moment", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);

        await tester.impersonateViaUserProfilePage(STUDENT_NAME);
        await profilePage.goto(STUDENT_ID);

        tester.isGoingTo("modify the 1st draft learning moment and keep it as a draft");
        const moment1 = activityFeed.locateMomentWithBody(moment1Body);
        await ActivityFeed.clickMomentModifyAction(moment1);

        moment1Body += " - modified";
        await learningMomentModify.typeBody(moment1Body);
        await learningMomentModify.clickSaveDraft();

        tester.isGoingTo("check the modified learning moment is saved as a draft");
        const modifiedMoment1 = activityFeed.locateMomentWithBody(moment1Body);
        await ActivityFeed.verifyMomentHasStatus(modifiedMoment1, EvidenceStatus.DRAFT);
    });

    test("As a student, submit a draft learning moment for approval from activity feed", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);

        await tester.impersonateViaUserProfilePage(STUDENT_NAME);
        await profilePage.goto(STUDENT_ID);

        tester.isGoingTo("submit the 2nd draft learning moment for approval from the activity feed");
        const moment2 = activityFeed.locateMomentWithBody(moment2Body);
        await ActivityFeed.clickMomentSubmitForApprovalAction(moment2);
        await ActivityFeed.verifyMomentHasStatus(moment2, EvidenceStatus.SUBMITTED);
    });

    test("As a student, create a learning moment and submit for approval immediately", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);

        await tester.impersonateViaUserProfilePage(STUDENT_NAME);
        await profilePage.goto(STUDENT_ID);

        tester.isGoingTo("As a student, submit a learning moment for approval");
        await activityFeed.clickShareLearningMoment();
        await activityFeed.shareLearningModal.clickShareText();

        await learningMomentModify.typeBody(moment3Body);
        await learningMomentModify.clickSubmitForApproval();

        tester.isGoingTo("check the learning moment requires approval");
        const moment = activityFeed.locateMomentWithBody(moment3Body);
        await ActivityFeed.verifyMomentHasStatus(moment, EvidenceStatus.SUBMITTED);
    });

    test("As a teacher, approve a draft learning moment", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);

        await tester.impersonateTeacher();
        await profilePage.goto(STUDENT_ID);

        const moment1 = activityFeed.locateMomentWithBody(moment1Body);

        tester.isGoingTo("check the learning moment is a draft");
        await ActivityFeed.verifyMomentHasStatus(moment1, EvidenceStatus.DRAFT);

        tester.isGoingTo("approve the learning moment via the modify page");
        await ActivityFeed.clickMomentModifyAction(moment1);
        await learningMomentModify.verifyCanRequestUpdate();
        await learningMomentModify.clickPublish();

        tester.isGoingTo("check the learning moment is published");
        await ActivityFeed.verifyMomentHasStatus(moment1, EvidenceStatus.PUBLISHED);
    });

    test("As a teacher, reject a learning moment that is awaiting approval", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);
        const learningMomentModify = new LearningMomentModify(tester, page);

        await tester.impersonateTeacher();
        await profilePage.goto(STUDENT_ID);

        const moment2 = activityFeed.locateMomentWithBody(moment2Body);

        tester.isGoingTo("check the learning moment requires approval");
        await ActivityFeed.verifyMomentHasStatus(moment2, EvidenceStatus.SUBMITTED);

        // From the activity feed, you should be able to approve (publish) the learning moment directly
        tester.isGoingTo("reject the learning moment via the activity feed");
        await ActivityFeed.clickMomentModifyAction(moment2);
        await learningMomentModify.clickRequestUpdate();
        await learningMomentModify.confirmRequestUpdate(requestUpdateReason);

        tester.isGoingTo("check the learning moment is rejected and has the right comment attached");
        await ActivityFeed.verifyMomentHasStatus(moment2, EvidenceStatus.REJECTED);
        await activityFeed.verifyMomentComment(requestUpdateReason);
    });

    test("As a parent, only see approved learning moments", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);

        await tester.impersonateViaUserProfilePage(Common.USER_JENNIREED);
        await profilePage.goto(STUDENT_ID);

        // Should only be able to see moment1, not moment2 or moment3
        tester.isGoingTo("check only the approved learning moment is visible");
        const moment1 = activityFeed.locateMomentWithBody(moment1Body);
        const moment2 = activityFeed.locateMomentWithBody(moment2Body);
        const moment3 = activityFeed.locateMomentWithBody(moment3Body);

        await expect(moment1).toBeVisible();
        await expect(moment2).not.toBeVisible();
        await expect(moment3).not.toBeVisible();
    });

    test("As a student, view teacher's feedback for a rejected learning moment", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);

        await tester.impersonateViaUserProfilePage(STUDENT_NAME);
        await profilePage.goto(STUDENT_ID);

        tester.isGoingTo("check the status of each learning moment");
        const moment1 = activityFeed.locateMomentWithBody(moment1Body);
        const moment2 = activityFeed.locateMomentWithBody(moment2Body);
        const moment3 = activityFeed.locateMomentWithBody(moment3Body);

        await ActivityFeed.verifyMomentHasStatus(moment1, EvidenceStatus.PUBLISHED);
        await ActivityFeed.verifyMomentHasStatus(moment2, EvidenceStatus.REJECTED);
        await ActivityFeed.verifyMomentHasStatus(moment3, EvidenceStatus.SUBMITTED);

        tester.isGoingTo("check the teacher's feedback is visible for the rejected learning moment");
        await activityFeed.verifyMomentComment(requestUpdateReason);
    });

    test("As a teacher, approve learning moment that is awaiting approval", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);

        await tester.impersonateTeacher();
        await profilePage.goto(STUDENT_ID);

        const moment3 = activityFeed.locateMomentWithBody(moment3Body);

        tester.isGoingTo("check the learning moment requires approval");
        await ActivityFeed.verifyMomentHasStatus(moment3, EvidenceStatus.SUBMITTED);

        tester.isGoingTo("approve a learning moment that is awaiting approval from the activity feed");
        await ActivityFeed.clickMomentPublishAction(moment3);

        tester.isGoingTo("check the learning moment is published");
        const publishedMoment = activityFeed.locateMomentWithBody(moment3Body);
        await ActivityFeed.verifyMomentHasStatus(publishedMoment, EvidenceStatus.PUBLISHED);
    });

    /**
     * Restore original settings/soft-delete new data as to not interfere with other tests
     */
    test.afterAll(async ({ db, api }) => {
        const momentIds = (await db.grabColumnFromDatabase("moment", "id", {
            "body LIKE": `%${MOMENT_BODY_PREFIX}%`,
            deleted_at: null,
        })) as number[];
        await Promise.all(momentIds.map((momentId) => api.deleteLearningMoment(momentId)));

        // await db.updateInDatabase("config", { value: 0 }, { name: CONFIG_LEARNING_MOMENTS_MODERATION_ENABLED });
        // await db.updateInDatabase("lu_year_level", { learning_moments_moderation: 0 }, { name: YEAR_LEVEL_NAME_ON });
    });
});

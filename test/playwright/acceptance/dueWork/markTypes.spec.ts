import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import AssessmentPage from "@schoolboxTest/playwright/support/page/dueWork/AssessmentPage";
import SubmitForm from "@schoolboxTest/playwright/support/page/dueWork/SubmitForm";
import StatusPage from "@schoolboxTest/playwright/support/page/dueWork/StatusPage";
import CourseModify from "@schoolboxTest/playwright/support/page/courses/CourseModify";
import FeedbackForm from "@schoolboxTest/playwright/support/page/dueWork/FeedbackForm";
import MarkbookPage from "@schoolboxTest/playwright/support/page/markbook/MarkbookPage";

test.describe("Due Work - Mark type tests", () => {
    test.describe.configure({ mode: "serial" });

    const instructor = "Mr Jack Coles";
    const learner = "Ms Jana Reed";
    const learnerId = 25;

    const unitName = `Test Unit ${Date.now()}`;
    let newUnitSpecId: number | null = null;
    let newUnitId: number | null = null;

    let classLink: string | null = null; // The homepage link for the class that we imported to

    const dueWorkBaseName = `Test Due Work ${Date.now()}`;
    const dueWorkName1 = `${dueWorkBaseName} - 1`;
    const dueWorkName2 = `${dueWorkBaseName} - 2`;
    const dueWorkName3 = `${dueWorkBaseName} - 3`;

    // Run before each test to ensure login and homepage navigation
    test.beforeEach(async ({ tester }) => {
        await tester.logout();
        await tester.loginAsAdmin();
    });

    /**
     * (Step 1) - Create 2 due works on a unit page and 1 on the homepage after importing it
     */
    test("Create activities", async ({ tester, page }) => {
        const courseModify = new CourseModify(page, tester);
        const assessmentForm = new AssessmentForm(tester, page);

        tester.isGoingTo("navigate to Year 9 English Modify course page");
        await courseModify.goto(6);

        tester.isGoingTo(`create a new unit with the name "${unitName}"`);
        newUnitSpecId = await courseModify.createNewUnit(unitName);

        tester.isGoingTo(`create a new due work with the name "${dueWorkName1}"`);
        await courseModify.clickNewActivityButton("due-work");

        // Create a second due work, self reflection without rubric, input percentage, display letter grade
        await assessmentForm.setTitle(dueWorkName1);
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.setSelfReflection(AssessmentForm.SELF_REFLECTION_FULL);
        await assessmentForm.setMarkType(AssessmentForm.MARK_TYPE_LETTER_GRADE);
        await assessmentForm.setInstructorMarkType(AssessmentForm.MARK_TYPE_PERCENTAGE);
        await assessmentForm.create();

        // Create a second due work, self reflection with rubric, input total mark, display percentage
        tester.isGoingTo(`create a new due work with the name "${dueWorkName2}"`);
        await courseModify.clickNewActivityButton("due-work");

        await assessmentForm.setTitle(dueWorkName2);
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.setSelfReflection(AssessmentForm.SELF_REFLECTION_FULL);
        await assessmentForm.setMarkType(AssessmentForm.MARK_TYPE_PERCENTAGE);
        await assessmentForm.setInstructorMarkType(AssessmentForm.MARK_TYPE_TOTAL_MARK);
        await assessmentForm.toggleUseRubric();
        await assessmentForm.addRubricLevel();
        await assessmentForm.toggleRubricIndicator(0, 426);
        await assessmentForm.fillRubricCriterionPoints(0, 426, 0, "1");
        await assessmentForm.fillRubricCriterionPoints(0, 426, 1, "2");
        await assessmentForm.toggleRubricIndicator(0, 420);
        await assessmentForm.fillRubricCriterionPoints(0, 420, 0, "1");
        await assessmentForm.fillRubricCriterionPoints(0, 420, 1, "2");
        await assessmentForm.create();

        // Now back on the course modify page, import the unit into the homepage
        tester.isGoingTo("import the new unit into 9A English");
        await courseModify.clickUnitMenu(newUnitSpecId);
        await courseModify.clickImportUnitToClasses();

        const classToImportRow = page
            .locator("#importForm li")
            .filter({ hasText: "9A English" })
            .filter({ hasText: "Displayed in Course Outline" });

        classLink = await classToImportRow.locator("a").first().getAttribute("href");
        if (classLink === null) {
            expect(classLink).not.toBeNull();
            throw new Error("classLink is null");
        }

        await classToImportRow.locator("label.checkbox-label").dispatchEvent("click"); // Must dispatch as click() can't directly target the ::before pseudo-element

        await classToImportRow.locator("input[id^=openDate]").fill("yesterday");
        await classToImportRow.locator("input[id^=closeDate]").fill("next week");

        await page.click("input[type=submit]");

        tester.isGoingTo("go to the class homepage");
        await page.goto(classLink);

        // Find the course outline and the unit we created inside it
        tester.isGoingTo("find the course outline and add a new due work to the unit");
        const homepage = new Homepage(page);
        const courseOutline = homepage.locateComponentByName("Course Outline");
        const courseOutlineId = await courseOutline.getAttribute("id");
        const courseOutlineUnit = courseOutline.locator("li[data-unit]").filter({ hasText: unitName });
        newUnitId = Number(await courseOutlineUnit.getAttribute("data-unit"));
        await courseOutlineUnit.getByTestId(`sbx-pop-out-${courseOutlineId}-unit-options${newUnitId}-button`).click();
        await courseOutlineUnit.locator("[data-dropdown-content] [data-add-button].icon-due-work").click();

        // Create a new due work, but now on the unit implementation
        tester.isGoingTo(`create a new due work with the name "${dueWorkName3}"`);
        await assessmentForm.setTitle(dueWorkName3);
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.setSelfReflection(AssessmentForm.SELF_REFLECTION_FULL);
        await assessmentForm.setMarkType(AssessmentForm.MARK_TYPE_NONE);
        await assessmentForm.setInstructorMarkType(AssessmentForm.MARK_TYPE_PERCENTAGE);
        await assessmentForm.toggleUseRubric();
        await assessmentForm.toggleRubricIndicator(0, 452);
        await assessmentForm.fillRubricCriterionPoints(0, 452, 0, "1");
        await assessmentForm.fillRubricCriterionPoints(0, 452, 1, "2");
        await assessmentForm.toggleRubricIndicator(0, 446);
        await assessmentForm.fillRubricCriterionPoints(0, 446, 0, "1");
        await assessmentForm.fillRubricCriterionPoints(0, 446, 1, "2");
        await assessmentForm.create();
    });

    /**
     * (Step 2) - Create submissions as Jana Reed for each assessment
     */
    test("Create submissions", async ({ tester, page }) => {
        if (newUnitId === null) throw new Error("newUnitId is null");

        const homepage = new Homepage(page);
        const assessmentPage = new AssessmentPage(tester, page);
        const feedbackForm = new FeedbackForm(tester, page);
        const submitForm = new SubmitForm(tester, page);

        await tester.impersonateViaUserProfilePage(learner);

        /**
         * @param activityName - The name of the activity to find
         * @returns The participant ID
         */
        async function createSelfAssessmentForActivity(activityName: string): Promise<number> {
            if (classLink === null) throw new Error("classLink is null");

            // Find the course outline and the unit we created inside it
            tester.isGoingTo("find the unit in the course outline");
            await page.goto(classLink);
            const courseOutline = homepage.locateComponentByName("Course Outline");
            const courseOutlineUnit = courseOutline.locator(`li[data-unit="${newUnitId}"]`);

            const activities = courseOutlineUnit.locator("li[data-unit-assessment]");
            await expect(activities).toHaveCount(3);

            const activity = activities.filter({ hasText: activityName });
            await activity.locator("a").first().click();

            await assessmentPage.clickSelfAcknowledge();
            await assessmentPage.clickSelfAcknowledgeProceed();

            const participantId = await page.locator("[data-feedback-form]").getAttribute("data-feedback-form");
            if (participantId === null) {
                expect(participantId).not.toBeNull();
                throw new Error("participantId is null");
            }

            return Number(participantId);
        }

        await test.step("Create a submission for the first due work", async () => {
            const participantId = await createSelfAssessmentForActivity(dueWorkName1);

            // Display mark type should be used (letter grade)
            await feedbackForm.setGradeDropdown("B+", participantId);
            await feedbackForm.setComment("test", participantId);
            await submitForm.submit();
        });

        await test.step("Create a submission for the second due work", async () => {
            const participantId = await createSelfAssessmentForActivity(dueWorkName2);

            await feedbackForm.selectRubricIndicatorByValue(452, "1");
            await feedbackForm.selectRubricIndicatorByValue(446, "2");

            // Display mark type should be used (percentage)
            await feedbackForm.expectRubricFinalSummaryMark("75 %");
            await feedbackForm.expectRubricSum("75 %");
            await feedbackForm.expectGrade("75 %", participantId);

            await feedbackForm.setComment("test", participantId);
            await submitForm.submit();
        });

        await test.step("Create a submission for the third due work", async () => {
            const participantId = await createSelfAssessmentForActivity(dueWorkName3);

            await feedbackForm.selectRubricIndicatorByValue(452, "1");
            await feedbackForm.selectRubricIndicatorByValue(446, "2");

            // Display mark type should be used (none)
            await feedbackForm.expectNoRubricFinalSummary();
            await feedbackForm.expectNoRubricSum();
            await feedbackForm.expectNoGradeSelector(participantId);

            await feedbackForm.setComment("test", participantId);
            await submitForm.submit();
        });
    });

    /**
     * (Step 3) - Mark the submissions as Jack Coles
     */
    test("Mark submissions", async ({ tester, page }) => {
        if (newUnitId === null) throw new Error("newUnitId is null");

        const homepage = new Homepage(page);
        const feedbackForm = new FeedbackForm(tester, page);
        const statusPage = new StatusPage(tester, page);

        await tester.impersonateViaUserProfilePage(instructor);

        /**
         * @param activityName - The name of the activity to find
         */
        async function viewStatusForActivity(activityName: string): Promise<void> {
            if (classLink === null) throw new Error("classLink is null");

            // Find the course outline and the unit we created inside it
            tester.isGoingTo("find the unit in the course outline");
            await page.goto(classLink);
            const courseOutline = homepage.locateComponentByName("Course Outline");
            const courseOutlineUnit = courseOutline.locator(`li[data-unit="${newUnitId}"]`);

            const activities = courseOutlineUnit.locator("li[data-unit-assessment]");
            await expect(activities).toHaveCount(3);

            tester.isGoingTo("click to view the status of the activity");
            const activity = activities.filter({ hasText: activityName });
            const activityId = await activity.getAttribute("data-unit-assessment");
            const courseOutlineId = await courseOutline.getAttribute("id");
            await activity.getByTestId(`sbx-pop-out-${courseOutlineId}-options-task${activityId}-button`).click();

            await activity.locator("[data-dropdown-content] a.icon-progress").click();
        }

        await test.step("Mark the first due work (via status page)", async () => {
            await viewStatusForActivity(dueWorkName1);

            // Find the participant row
            const participantRow = page.locator(`li[data-participant][data-participant-name="${learner}"]`);

            // Input mark type should be used (percentage)
            const gradeInput = participantRow.locator(StatusPage.SELECTOR_MARK);
            await expect(gradeInput).toHaveAttribute("placeholder", "Percentage mark");

            await statusPage.gradeSubmission(learnerId, 75, "test");
        });

        await test.step("Mark the second due work (via inline grader)", async () => {
            await viewStatusForActivity(dueWorkName2);

            await statusPage.clickParticipantJellyTot(learnerId);
            await statusPage.clickViewAndGradeSubmission(learnerId);

            await feedbackForm.selectRubricIndicatorByValue(452, "1");
            await feedbackForm.selectRubricIndicatorByValue(446, "1");

            // Input mark type should be used (total mark)
            await feedbackForm.expectRubricFinalSummaryMark("2 / 4 (3 / 4 given in Self-assessment)");
            await feedbackForm.expectRubricSum("2 / 4");
            await feedbackForm.expectGrade("2 / 4", learnerId);

            // Display mark type should be used for the self assessment mark (percentage)
            await feedbackForm.expectSelfAssessmentMark("75 %");

            await feedbackForm.setComment("test", learnerId);
            await feedbackForm.publish();
        });

        await test.step("Mark the third due work (via inline grader)", async () => {
            await viewStatusForActivity(dueWorkName3);

            await statusPage.clickParticipantJellyTot(learnerId);
            await statusPage.clickViewAndGradeSubmission(learnerId);

            await feedbackForm.selectRubricIndicatorByValue(452, "1");
            await feedbackForm.selectRubricIndicatorByValue(446, "1");

            // Input mark type should be used (percentage)
            await feedbackForm.expectRubricFinalSummaryMark("50 % ( given in Self-assessment)"); // NOTE: The self-assessment mark here is a bug, it should be blank. See SA-19963
            await feedbackForm.expectRubricSum("50 %");
            await feedbackForm.expectGrade("50 %", learnerId);

            // Displey mark type should be used for the self assessment mark (none)
            await feedbackForm.expectNoSelfAssessmentMark();

            await feedbackForm.setComment("test", learnerId);
            await feedbackForm.publish();
        });
    });

    /**
     * (Step 4) - Submission history
     */
    test("Submission history", async ({ tester, page }) => {
        if (classLink === null) throw new Error("classLink is null");
        if (newUnitId === null) throw new Error("newUnitId is null");

        const homepage = new Homepage(page);
        const statusPage = new StatusPage(tester, page);

        await tester.impersonateViaUserProfilePage(instructor);

        /**
         * @param activityName - The name of the activity to find
         */
        async function viewStatusForActivity(activityName: string): Promise<void> {
            if (classLink === null) throw new Error("classLink is null");

            // Find the course outline and the unit we created inside it
            tester.isGoingTo("find the unit in the course outline");
            await page.goto(classLink);
            const courseOutline = homepage.locateComponentByName("Course Outline");
            const courseOutlineUnit = courseOutline.locator(`li[data-unit="${newUnitId}"]`);

            const activities = courseOutlineUnit.locator("li[data-unit-assessment]");
            await expect(activities).toHaveCount(3);

            tester.isGoingTo("click to view the status of the activity");
            const activity = activities.filter({ hasText: activityName });
            const activityId = await activity.getAttribute("data-unit-assessment");
            const courseOutlineId = await courseOutline.getAttribute("id");
            await activity.getByTestId(`sbx-pop-out-${courseOutlineId}-options-task${activityId}-button`).click();

            await activity.locator("[data-dropdown-content] a.icon-progress").click();
        }

        await test.step("Check submission history for the first due work", async () => {
            await viewStatusForActivity(dueWorkName1);

            await statusPage.clickParticipantJellyTot(learnerId);
            await statusPage.clickSubmissionHistory(learnerId);

            // Display mark type should be used (letter grade)
            const summary = page.getByTestId("assessment-feedback-summary");
            const mainMark = summary.getByTestId("assessment-feedback-mark");

            await expect(mainMark).toHaveText("B+");

            // we submitted this via the status page, so there'll be two independent submission history items
            const submissionHistory = page.getByTestId("assessment-submission-history");
            await expect(submissionHistory).toHaveCount(2);

            const submissionHistory1Mark = submissionHistory.first().getByTestId("assessment-feedback-mark");
            const submissionHistory2Mark = submissionHistory.last().getByTestId("assessment-feedback-mark");

            await expect(submissionHistory1Mark).toHaveText("B+"); // The final mark
            await expect(submissionHistory2Mark).toHaveText("B+"); // The self-assessment mark
        });

        await test.step("Check submission history for the second due work", async () => {
            await viewStatusForActivity(dueWorkName2);

            await statusPage.clickParticipantJellyTot(learnerId);
            await statusPage.clickSubmissionHistory(learnerId);

            // Display mark type should be used (percentage)
            const summary = page.getByTestId("assessment-feedback-summary");
            const mainMark = summary.getByTestId("assessment-feedback-mark");

            await expect(mainMark).toHaveText("50 %");

            const submissionHistory = page.getByTestId("assessment-submission-history");
            const submissionHistoryMarks = submissionHistory.getByTestId("assessment-feedback-mark");

            await expect(submissionHistoryMarks).toHaveCount(2);
            await expect(submissionHistoryMarks.first()).toHaveText("75 %"); // The self-assessment mark
            await expect(submissionHistoryMarks.last()).toHaveText("50 %"); // The final mark
        });

        await test.step("Check submission history for the third due work", async () => {
            await viewStatusForActivity(dueWorkName3);

            await statusPage.clickParticipantJellyTot(learnerId);
            await statusPage.clickSubmissionHistory(learnerId);

            // Display mark type should be used (none)
            const summary = page.getByTestId("assessment-feedback-summary");
            const mainMark = summary.getByTestId("assessment-feedback-mark");

            await expect(mainMark).not.toBeVisible();

            const submissionHistory = page.getByTestId("assessment-submission-history");
            const submissionHistoryMarks = submissionHistory.getByTestId("assessment-feedback-mark");

            await expect(submissionHistoryMarks).toHaveCount(0);
        });
    });

    /**
     * (Step 5) - Course Markbook (via course outline component)
     */
    test("Course Markbook", async ({ tester, page }) => {
        if (classLink === null) throw new Error("classLink is null");

        const homepage = new Homepage(page);
        const markbook = new MarkbookPage(page);

        await tester.impersonateViaUserProfilePage(instructor);

        tester.isGoingTo("navigate to the course markbook page");
        await page.goto(classLink);
        const courseOutline = homepage.locateComponentByName("Course Outline");
        const courseOutlineId = await courseOutline.getAttribute("id");
        const courseOutlineUnit = courseOutline.locator("li[data-unit]").filter({ hasText: unitName });
        newUnitId = Number(await courseOutlineUnit.getAttribute("data-unit"));
        await courseOutlineUnit.getByTestId(`sbx-pop-out-${courseOutlineId}-unit-options${newUnitId}-button`).click();
        await courseOutlineUnit.locator("[data-dropdown-content] a.icon-curriculum").click();

        const assessmentId1 = await markbook.getAssessmentId(dueWorkName1);
        const assessmentId2 = await markbook.getAssessmentId(dueWorkName2);
        const assessmentId3 = await markbook.getAssessmentId(dueWorkName3);

        await test.step("Input Mark Type", async () => {
            await markbook.showInputMarks();
            await markbook.validateInputMark(learnerId, assessmentId1, "75 %");
            await markbook.validateInputMark(learnerId, assessmentId2, "2 / 4");
            await markbook.validateInputMark(learnerId, assessmentId3, "50 %");
        });

        await test.step("Display Mark Type", async () => {
            await markbook.showDisplayMarks();
            await markbook.validateDisplayMark(learnerId, assessmentId1, "B+");
            await markbook.validateDisplayMark(learnerId, assessmentId2, "50 %");
            await markbook.validateDisplayMark(learnerId, assessmentId3, "–");
        });
    });

    /**
     * (Step 6) - Class Markbook
     */
    test("Class Markbook", async ({ tester, page }) => {
        if (classLink === null) throw new Error("classLink is null");

        const homepage = new Homepage(page);
        const markbook = new MarkbookPage(page);

        await tester.impersonateViaUserProfilePage(instructor);

        tester.isGoingTo("navigate to the class markbook page");
        await page.goto(classLink);
        await homepage.openLinkMenu();
        await page.locator("[data-dropdown-content] a.icon-curriculum").filter({ hasText: "Class Markbook" }).click();

        const assessmentId1 = await markbook.getAssessmentId(dueWorkName1);
        const assessmentId2 = await markbook.getAssessmentId(dueWorkName2);
        const assessmentId3 = await markbook.getAssessmentId(dueWorkName3);

        await test.step("Input Mark Type", async () => {
            await markbook.showInputMarks();
            await markbook.validateInputMark(learnerId, assessmentId1, "75 %");
            await markbook.validateInputMark(learnerId, assessmentId2, "2 / 4");
            await markbook.validateInputMark(learnerId, assessmentId3, "50 %");
        });

        await test.step("Display Mark Type", async () => {
            await markbook.showDisplayMarks();
            await markbook.validateDisplayMark(learnerId, assessmentId1, "B+");
            await markbook.validateDisplayMark(learnerId, assessmentId2, "50 %");
            await markbook.validateDisplayMark(learnerId, assessmentId3, "–");
        });
    });
});

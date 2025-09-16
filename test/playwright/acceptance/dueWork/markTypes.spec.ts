import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import AssessmentPage from "@schoolboxTest/playwright/support/page/dueWork/AssessmentPage";
import SubmitForm from "@schoolboxTest/playwright/support/page/dueWork/SubmitForm";
import StatusPage from "@schoolboxTest/playwright/support/page/dueWork/StatusPage";
import CourseModify from "@schoolboxTest/playwright/support/page/courses/CourseModify";
import FeedbackForm from "@schoolboxTest/playwright/support/page/dueWork/FeedbackForm";
import MarkbookPage from "@schoolboxTest/playwright/support/page/markbook/MarkbookPage";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";
import GradesPage from "@schoolboxTest/playwright/support/page/grades/GradesPage";
import ActivityFeed from "@schoolboxTest/playwright/support/page/profilePage/ActivityFeed";
import CourseOutline from "@schoolboxTest/playwright/support/page/homepage/CourseOutline";

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
        await assessmentForm.enableDualMarkTypes();
        await assessmentForm.setMarkType(AssessmentForm.MARK_TYPE_LETTER_GRADE);
        await assessmentForm.setInstructorMarkType(AssessmentForm.MARK_TYPE_PERCENTAGE);
        await assessmentForm.clickCreate();

        // Create a second due work, self reflection with rubric, input total mark, display percentage
        tester.isGoingTo(`create a new due work with the name "${dueWorkName2}"`);
        await courseModify.clickNewActivityButton("due-work");

        await assessmentForm.setTitle(dueWorkName2);
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.setSelfReflection(AssessmentForm.SELF_REFLECTION_FULL);
        await assessmentForm.enableDualMarkTypes();
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
        await assessmentForm.clickCreate();

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
        const homepage = new Homepage(tester, page);
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
        await assessmentForm.enableDualMarkTypes();
        await assessmentForm.setMarkType(AssessmentForm.MARK_TYPE_NONE);
        await assessmentForm.setInstructorMarkType(AssessmentForm.MARK_TYPE_PERCENTAGE);
        await assessmentForm.toggleUseRubric();
        await assessmentForm.toggleRubricIndicator(0, 452);
        await assessmentForm.fillRubricCriterionPoints(0, 452, 0, "1");
        await assessmentForm.fillRubricCriterionPoints(0, 452, 1, "2");
        await assessmentForm.toggleRubricIndicator(0, 446);
        await assessmentForm.fillRubricCriterionPoints(0, 446, 0, "1");
        await assessmentForm.fillRubricCriterionPoints(0, 446, 1, "2");
        await assessmentForm.clickCreate();
    });

    /**
     * (Step 2) - Create submissions as Jana Reed for each assessment
     */
    test("Create submissions", async ({ tester, page }) => {
        const homepageCourseOutline = new CourseOutline(tester, page);
        const assessmentPage = new AssessmentPage(tester, page);
        const feedbackForm = new FeedbackForm(tester, page);
        const submitForm = new SubmitForm(tester, page);

        await tester.impersonateViaUserProfilePage(learner);

        await test.step("Create a submission for the first due work", async () => {
            if (classLink === null) throw new Error("classLink is null");
            if (newUnitId === null) throw new Error("newUnitId is null");
            await page.goto(classLink);
            await homepageCourseOutline.viewUnitActivitySummary(newUnitId, dueWorkName1);
            await assessmentPage.clickSelfAcknowledge();
            await assessmentPage.clickSelfAcknowledgeProceed();

            // Display mark type should be used (letter grade)
            const participantId = await feedbackForm.getParticipantId();
            await feedbackForm.setGradeDropdown("B+", participantId);
            await feedbackForm.setComment("test", participantId);
            await submitForm.submit();
        });

        await test.step("Create a submission for the second due work", async () => {
            if (classLink === null) throw new Error("classLink is null");
            if (newUnitId === null) throw new Error("newUnitId is null");
            await page.goto(classLink);
            await homepageCourseOutline.viewUnitActivitySummary(newUnitId, dueWorkName2);
            await assessmentPage.clickSelfAcknowledge();
            await assessmentPage.clickSelfAcknowledgeProceed();

            await feedbackForm.selectRubricIndicatorByValue(452, "1");
            await feedbackForm.selectRubricIndicatorByValue(446, "2");

            // Display mark type should be used (percentage)
            const participantId = await feedbackForm.getParticipantId();
            await feedbackForm.expectRubricFinalSummaryMark("75 %");
            await feedbackForm.expectRubricSum("75 %");
            await feedbackForm.expectGrade("75 %", participantId);

            await feedbackForm.setComment("test", participantId);
            await submitForm.submit();
        });

        await test.step("Create a submission for the third due work", async () => {
            if (classLink === null) throw new Error("classLink is null");
            if (newUnitId === null) throw new Error("newUnitId is null");
            await page.goto(classLink);
            await homepageCourseOutline.viewUnitActivitySummary(newUnitId, dueWorkName3);
            await assessmentPage.clickSelfAcknowledge();
            await assessmentPage.clickSelfAcknowledgeProceed();

            await feedbackForm.selectRubricIndicatorByValue(452, "1");
            await feedbackForm.selectRubricIndicatorByValue(446, "2");

            // Display mark type should be used (none)
            const participantId = await feedbackForm.getParticipantId();
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
        const homepageCourseOutline = new CourseOutline(tester, page);
        const feedbackForm = new FeedbackForm(tester, page);
        const statusPage = new StatusPage(tester, page);

        await tester.impersonateViaUserProfilePage(instructor);

        await test.step("Mark the first due work (via status page)", async () => {
            if (classLink === null) throw new Error("classLink is null");
            if (newUnitId === null) throw new Error("newUnitId is null");
            await page.goto(classLink);
            await homepageCourseOutline.viewUnitActivityStatus(newUnitId, dueWorkName1);

            // Find the participant row
            const participantRow = page.locator(`li[data-participant][data-participant-name="${learner}"]`);

            // Input mark type should be used (percentage)
            const gradeInput = participantRow.locator(StatusPage.SELECTOR_MARK);
            await expect(gradeInput).toHaveAttribute("placeholder", "Percentage mark");

            await statusPage.gradeSubmission(learnerId, 75, "test");
        });

        await test.step("Mark the second due work (via inline grader)", async () => {
            if (classLink === null) throw new Error("classLink is null");
            if (newUnitId === null) throw new Error("newUnitId is null");
            await page.goto(classLink);
            await homepageCourseOutline.viewUnitActivityStatus(newUnitId, dueWorkName2);

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
            if (classLink === null) throw new Error("classLink is null");
            if (newUnitId === null) throw new Error("newUnitId is null");
            await page.goto(classLink);
            await homepageCourseOutline.viewUnitActivityStatus(newUnitId, dueWorkName3);

            await statusPage.clickParticipantJellyTot(learnerId);
            await statusPage.clickViewAndGradeSubmission(learnerId);

            await feedbackForm.selectRubricIndicatorByValue(452, "1");
            await feedbackForm.selectRubricIndicatorByValue(446, "1");

            // Input mark type should be used (percentage)
            await feedbackForm.expectRubricFinalSummaryMark("50 %");
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

        const homepageCourseOutline = new CourseOutline(tester, page);
        const statusPage = new StatusPage(tester, page);

        await tester.impersonateViaUserProfilePage(instructor);

        await test.step("Check submission history for the first due work", async () => {
            if (classLink === null) throw new Error("classLink is null");
            if (newUnitId === null) throw new Error("newUnitId is null");
            await page.goto(classLink);
            await homepageCourseOutline.viewUnitActivityStatus(newUnitId, dueWorkName1);

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
            if (classLink === null) throw new Error("classLink is null");
            if (newUnitId === null) throw new Error("newUnitId is null");
            await page.goto(classLink);
            await homepageCourseOutline.viewUnitActivityStatus(newUnitId, dueWorkName2);

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
            if (classLink === null) throw new Error("classLink is null");
            if (newUnitId === null) throw new Error("newUnitId is null");
            await page.goto(classLink);
            await homepageCourseOutline.viewUnitActivityStatus(newUnitId, dueWorkName3);

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

        const homepage = new Homepage(tester, page);
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

        const homepage = new Homepage(tester, page);
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

    /**
     * (Step 7) - Grades Page (as an instructor)
     */
    test("Grades Page (as an instructor)", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const gradesPage = new GradesPage(tester, page);

        await tester.impersonateViaUserProfilePage(instructor);

        tester.isGoingTo("navigate to the grades page for Jana Reed");
        await tester.searchForUser(learner);
        await profilePage.clickButton("Grade");

        tester.isGoingTo("click the row for the relevant homepage (9A English)");
        await gradesPage.clickSubject("9A English");

        await gradesPage.expectGradesVisibility("instructor");

        await test.step("Input Mark Type (initial load)", async () => {
            await gradesPage.expectAssessmentMark(dueWorkName1, "75 %");
            await gradesPage.expectAssessmentMark(dueWorkName2, "2 / 4");
            await gradesPage.expectAssessmentMark(dueWorkName3, "50 %");
        });

        await test.step("Display Mark Type", async () => {
            await gradesPage.viewStudentGrades();
            await gradesPage.expectAssessmentMark(dueWorkName1, "B+");
            await gradesPage.expectAssessmentMark(dueWorkName2, "50 %");
            await gradesPage.expectAssessmentMark(dueWorkName3, null);
        });

        // Then re-check input mark type via the button
        await test.step("Input Mark Type", async () => {
            await gradesPage.viewInstructorGrades();
            await gradesPage.expectAssessmentMark(dueWorkName1, "75 %");
            await gradesPage.expectAssessmentMark(dueWorkName2, "2 / 4");
            await gradesPage.expectAssessmentMark(dueWorkName3, "50 %");
        });
    });

    /**
     * (Step 8) - Grades Page (as a student)
     */
    test("Grades Page (as a student)", async ({ tester, page }) => {
        const gradesPage = new GradesPage(tester, page);

        await tester.impersonateViaUserProfilePage(learner);

        tester.isGoingTo("navigate to the grades page for the current user");
        await page.locator(".top-menu a").filter({ hasText: "Grades" }).click();

        tester.isGoingTo("click the row for the relevant homepage (9A English)");
        await gradesPage.clickSubject("9A English");

        await gradesPage.expectAssessmentMark(dueWorkName1, "B+");
        await gradesPage.expectAssessmentMark(dueWorkName2, "50 %");
        await gradesPage.expectAssessmentMark(dueWorkName3, null);
    });

    /**
     * (Step 9) - Profile Page / Activity Feed
     */
    test("Profile Page / Activity Feed", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);
        const activityFeed = new ActivityFeed(tester, page);

        await tester.impersonateViaUserProfilePage(learner);

        tester.isGoingTo("navigate to the profile page");
        await profilePage.goto(learnerId);

        tester.isGoingTo("check that the 3 assessments in the activity feed use the display mark type");
        await activityFeed.expectActivityFeedItemMark(dueWorkName1, "B+");
        await activityFeed.expectActivityFeedItemMark(dueWorkName2, "50 %");
        await activityFeed.expectActivityFeedItemMark(dueWorkName3, null);
    });

    test.afterAll(async ({ db, api }) => {
        // Soft-delete data to not interfere with other tests
        const folderIds = await api.deleteFoldersByName(db, "Test Unit %");
        const courseUnitSpecIds = (await db.grabColumnFromDatabase("course_unit_specification", "id", {
            folder_id: folderIds,
        })) as number[];

        await db.updateInDatabase(
            "course_unit_specification",
            { deleted_at: "2022-01-15 11:12:34" },
            { folder_id: folderIds },
        );
        await db.updateInDatabase(
            "course_unit_implementation",
            { deleted_at: "2022-01-15 11:12:34" },
            { course_unit_specification_id: courseUnitSpecIds },
        );
        await db.updateInDatabase(
            "assessment",
            { deleted_at: "2022-01-15 11:12:34" },
            { "title LIKE": "Test Due Work %" },
        );
    });
});

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import CourseModify from "@schoolboxTest/playwright/support/page/courses/CourseModify";

test.describe("Due Work - Mark type tests", () => {
    test.describe.configure({ mode: "serial" });

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
        await assessmentForm.toggleRubricIndicator(0, 426);
        await assessmentForm.fillRubricCriterionPoints(0, 426, 0, "1");
        await assessmentForm.fillRubricCriterionPoints(0, 426, 0, "2");
        await assessmentForm.toggleRubricIndicator(0, 420);
        await assessmentForm.fillRubricCriterionPoints(0, 420, 0, "1");
        await assessmentForm.fillRubricCriterionPoints(0, 420, 0, "2");
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
        const courseOutlineUnit = courseOutline.locator(`li[data-unit]`).filter({ hasText: unitName });
        newUnitId = Number(await courseOutlineUnit.getAttribute("data-unit"));
        await courseOutlineUnit.getByTestId(`sbx-pop-out-${courseOutlineId}-unit-options${newUnitId}-button`).click();
        await courseOutlineUnit.locator("[data-dropdown-content] [data-add-button].icon-due-work").click();

        // Create a new due work, but now on the unit implementation
        tester.isGoingTo(`create a new due work with the name "${dueWorkName3}"`);
        await assessmentForm.setTitle(dueWorkName3);
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await assessmentForm.setSelfReflection(AssessmentForm.SELF_REFLECTION_FULL);
        await assessmentForm.setMarkType(AssessmentForm.MARK_TYPE_PERCENTAGE);
        await assessmentForm.setInstructorMarkType(AssessmentForm.MARK_TYPE_NONE);
        await assessmentForm.toggleUseRubric();
        await assessmentForm.toggleRubricIndicator(0, 452);
        await assessmentForm.fillRubricCriterionPoints(0, 452, 0, "1");
        await assessmentForm.fillRubricCriterionPoints(0, 452, 0, "2");
        await assessmentForm.toggleRubricIndicator(0, 446);
        await assessmentForm.fillRubricCriterionPoints(0, 446, 0, "1");
        await assessmentForm.fillRubricCriterionPoints(0, 446, 0, "2");
        await assessmentForm.create();
    });
});

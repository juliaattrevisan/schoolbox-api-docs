import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import QuestionBankPage from "@schoolboxTest/playwright/support/page/survey/QuestionBankPage";
import SummaryPage from "@schoolboxTest/playwright/support/page/survey/SummaryPage";
import SectionForm from "@schoolboxTest/playwright/support/page/survey/SectionForm";
import SurveyForm from "@schoolboxTest/playwright/support/page/survey/SurveyForm";
import QuestionForm from "@schoolboxTest/playwright/support/page/survey/QuestionForm";
import Common from "@schoolboxTest/playwright/support/page/Common";

/**
 * creates and modify a survey, add a question to the question bank
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test questionBank.spec.ts
 */

test.describe("Question Bank tests", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    /**
     * Tests adding and modifying survey questions in question bank
     */
    test("Question Bank", async ({ tester, page, db }) => {
        const classId = Common.CLASS_ID_9AENG;
        const surveyTitle = `Cest Survey One ${Common.genText(6)}`;
        const sectionTitle = `Cest Section One ${Common.genText(6)}`;
        const question1 = `Question ${Common.genText(100)}`;
        const question1Type = QuestionForm.TYPE_SHORT_ANSWER;
        const question2 = `Question ${Common.genText(50)}`;
        const question2Type = QuestionForm.TYPE_SHORT_SIGNATURE;
        const question3 = `Question ${Common.genText(80)}`;
        const question3Type = QuestionForm.TYPE_YESNO;
        const categoryId = QuestionBankPage.CATEGORY_ELECTIVE_SELECTION;

        const surveyForm = new SurveyForm(page);
        const questionBankPage = new QuestionBankPage(page);
        const summaryPage = new SummaryPage(page);
        const sectionForm = new SectionForm(page);
        const questionForm = new QuestionForm(page, tester, db);

        tester.isGoingTo("create survey");
        await surveyForm.goto(classId);
        await surveyForm.setTitle(surveyTitle);
        await surveyForm.create();

        tester.isGoingTo("create section");
        await sectionForm.setTitle(sectionTitle);
        await sectionForm.create();
        await page.waitForTimeout(2000);
        const assessId = await sectionForm.getAssessId();
        const sectionId = await sectionForm.getSectionId();

        tester.isGoingTo("create question");
        await questionForm.setType(question1Type);
        await questionForm.setQuestion(question1);
        await questionForm.create();
        await page.waitForTimeout(2000);
        const questionId = await questionForm.getQuestionId(question1Type, sectionId);

        tester.isGoingTo("back to survey summary page");
        await summaryPage.goto(assessId);
        await expect(page.locator(`h1:has-text("${surveyTitle}")`)).toBeVisible();

        tester.isGoingTo("add the question to question bank");
        await summaryPage.addToQuestionBank(questionId, categoryId);

        tester.isGoingTo("find the question in question bank and click modify");
        await summaryPage.clickAddFromQuestionBank(sectionId);
        await questionBankPage.clickCategory(categoryId);
        await questionBankPage.clickModify(question1);

        tester.isGoingTo("save without any change");
        await questionForm.modify();

        tester.isGoingTo("back to survey summary page");
        await summaryPage.goto(assessId);
        await expect(page.locator(`h1:has-text("${surveyTitle}")`)).toBeVisible();

        tester.isGoingTo("create another question");
        await summaryPage.clickNewQuestion(sectionId);

        tester.isGoingTo("save the question");
        await questionForm.setType(question2Type);
        await questionForm.setQuestion(question2);
        await questionForm.create();
        await page.waitForTimeout(2000);

        const question2Id = await questionForm.getQuestionId(question2Type, sectionId);

        tester.isGoingTo("back to survey summary page");
        await summaryPage.goto(assessId);
        await expect(page.locator(`h1:has-text("${surveyTitle}")`)).toBeVisible();

        tester.isGoingTo("add the question to question bank");
        await summaryPage.addToQuestionBank(question2Id, categoryId);

        tester.isGoingTo("find the question in question bank and click modify");
        await summaryPage.clickAddFromQuestionBank(sectionId);
        await questionBankPage.clickCategory(categoryId);
        await questionBankPage.clickModify(question2);

        tester.isGoingTo("save without any change");
        await questionForm.modify();

        tester.isGoingTo("back to survey summary page");
        await summaryPage.goto(assessId);
        await expect(page.locator(`h1:has-text("${surveyTitle}")`)).toBeVisible();

        tester.isGoingTo("create one more question");
        await summaryPage.clickNewQuestion(sectionId);

        tester.isGoingTo("save the question");
        await questionForm.setType(question3Type);
        await questionForm.setQuestion(question3);
        await questionForm.create();
        await page.waitForTimeout(2000);

        const question3Id = await questionForm.getQuestionId(question3Type, sectionId);

        tester.isGoingTo("back to survey summary page");
        await summaryPage.goto(assessId);
        await expect(page.locator(`h1:has-text("${surveyTitle}")`)).toBeVisible();

        tester.isGoingTo("add the question to question bank");
        await summaryPage.addToQuestionBank(question3Id, categoryId);

        tester.isGoingTo("find the question in question bank and click modify");
        await summaryPage.clickAddFromQuestionBank(sectionId);
        await questionBankPage.clickCategory(categoryId);
        await questionBankPage.clickModify(question3);

        tester.isGoingTo("save without any change");
        await questionForm.modify();
    });

    /**
     * Soft delete data tp not affect other tests
     */

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update delete_at for the assess, assess_question_bank and assess_question table so it does not impact the tests",
        );
        await db.updateInDatabase(
            "assess",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Cest Survey One%" },
        );
        await db.updateInDatabase(
            "assess_question_bank",
            { deleted_at: "2022-01-15 11:22:34" },
            { "question like": "%Question%" },
        );
        await db.updateInDatabase(
            "assess_question",
            { deleted_at: "2022-01-15 11:22:34" },
            { "question like": "%Question%" },
        );
    });
});

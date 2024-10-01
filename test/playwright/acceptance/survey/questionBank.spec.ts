import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import QuestionBankPage from "@schoolboxTest/playwright/support/page/survey/QuestionBankPage";

test.describe("Question Bank tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("Create a question", async ({ page }) => {
        const questionBankPage = new QuestionBankPage(page);
        await page.goto("/survey/questionbank/contents/10/128/146");
        await questionBankPage.clickModify("Do you read at home?");
    });
});

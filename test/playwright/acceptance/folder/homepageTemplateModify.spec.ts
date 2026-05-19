/**
 * Tests the homepage template field displayed when modifying an existing folder.
 * 1. Provision sales.tar.gz data
 * 2. Execute DOCKER=1 npx playwright test homepageTemplateModify.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";

test.describe("Folder homepage template modify form", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("shows the current homepage template name in the keep existing option", async ({ page }) => {
        await page.goto("/resource/folder/13/modify");

        const homepageTemplateSelect = page.getByTestId(CreateFolder.DATA_TEST_FOLDER_FORM_SELECT_TEMPLATE);
        await expect(homepageTemplateSelect).toHaveValue("0");
        await expect(homepageTemplateSelect.locator("option:checked")).toHaveText("Keep Existing (Course)");
        await expect(homepageTemplateSelect.locator("option", { hasText: "Keep Existing (Course)" })).toHaveCount(1);
    });
});

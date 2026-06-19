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

    const templateFolders = [
        { folderId: 66, templateName: "Blank" },
        { folderId: 98, templateName: "Unit" },
    ];

    for (const { folderId, templateName } of templateFolders) {
        test(`shows ${templateName} as the current homepage template name`, async ({ page }) => {
            await page.goto(`/resource/folder/${folderId}/modify`);

            const homepageTemplateSelect = page.getByTestId(CreateFolder.DATA_TEST_FOLDER_FORM_SELECT_TEMPLATE);
            const keepExistingLabel = `Keep Existing (${templateName})`;
            await expect(homepageTemplateSelect).toHaveValue("0");
            await expect(homepageTemplateSelect.locator("option:checked")).toHaveText(keepExistingLabel);
            await expect(homepageTemplateSelect.locator("option", { hasText: keepExistingLabel })).toHaveCount(1);
        });
    }
});

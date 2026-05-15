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

    test("shows the current homepage template name in the keep existing option", async ({ tester, page, db, api }) => {
        const createFolder = new CreateFolder(tester, page);
        const folderName = `Homepage Template Modify ${Date.now()}`;

        try {
            await createFolder.createFolder(
                folderName,
                "Blank",
                db,
                [
                    [1, "write"],
                    [2, "write"],
                    [3, "read"],
                    [4, "read"],
                    [5, "read"],
                    [6, "read"],
                ],
                "",
            );

            const folderId = (await db.grabFromDatabase("folder", "id", {
                deleted_at: null,
                name: folderName,
            })) as number;

            await page.goto(`/resource/folder/${folderId}/modify`);

            const homepageTemplateSelect = page.getByTestId(CreateFolder.DATA_TEST_FOLDER_FORM_SELECT_TEMPLATE);
            await expect(homepageTemplateSelect).toHaveValue("0");
            await expect(homepageTemplateSelect.locator("option:checked")).toHaveText("Keep Existing (Blank)");
            await expect(homepageTemplateSelect.locator("option", { hasText: "Keep Existing (Blank)" })).toHaveCount(1);
        } finally {
            await api.deleteFoldersByName(db, `${folderName}%`);
        }
    });
});

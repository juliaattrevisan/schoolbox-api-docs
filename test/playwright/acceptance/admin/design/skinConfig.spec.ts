/**
 * Tests Sonar UI Skin Config validation, live previews, undo, and persisted Accent styling.
 * 1. Provision sales.tar.gz data
 * 2. Execute DOCKER=1 npx playwright test skinConfig.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import SkinConfig from "@schoolboxTest/playwright/support/page/admin/design/SkinConfig";
import { getCssCustomProperty } from "@js/util/cssUtils";

const ADVANCED_UI_CONFIG = "advanced_ui_enabled";

test.describe("Skin Config", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("requires valid colour contrast before saving", async ({ tester, page, db }) => {
        const skinConfig = new SkinConfig(page);
        const configName = "skin_colour_menu_foreground";
        const originalValue = (await db.grabFromDatabase("config", "value", { name: configName })) as string;

        try {
            await db.updateInDatabase("config", { value: "1" }, { name: ADVANCED_UI_CONFIG });
            await skinConfig.goto();

            tester.isGoingTo("confirm invalid Top Menu colour contrast prevents saving");
            await expect(skinConfig.getValidationError(configName)).toContainText("Insufficient contrast");
            await skinConfig.save();
            await expect(skinConfig.getSaveSuccessToast()).not.toBeVisible();

            tester.isGoingTo("change Top Menu Foreground to white and save");
            await tester.common.setColour(skinConfig.getField(configName), "rgb(255, 255, 255)");
            await expect(skinConfig.getValidationError(configName)).not.toBeVisible();
            await skinConfig.save();
            await expect(skinConfig.getSaveSuccessToast()).toBeVisible();
        } finally {
            try {
                await db.updateInDatabase("config", { value: originalValue }, { name: configName });
            } finally {
                await db.updateInDatabase("config", { value: "0" }, { name: ADVANCED_UI_CONFIG });
            }
        }
    });

    test("previews container and field corner radii", async ({ tester, page, db }) => {
        const skinConfig = new SkinConfig(page);

        try {
            await db.updateInDatabase("config", { value: "1" }, { name: ADVANCED_UI_CONFIG });
            await skinConfig.goto();

            tester.isGoingTo("preview no container radius and a large field radius");
            await tester.common.selectOption(skinConfig.getField("skin_container_corner_radius"), "None");
            await tester.common.selectOption(skinConfig.getField("skin_field_corner_radius"), "Large");

            await expect
                .poll(() => page.evaluate(getCssCustomProperty, "--sbx-config-container-corner-radius"))
                .toBe("0");
            await expect
                .poll(() => page.evaluate(getCssCustomProperty, "--sbx-config-field-corner-radius"))
                .toBe("0.75rem");
        } finally {
            await db.updateInDatabase("config", { value: "0" }, { name: ADVANCED_UI_CONFIG });
        }
    });

    test("previews a large container drop shadow", async ({ tester, page, db }) => {
        const skinConfig = new SkinConfig(page);

        try {
            await db.updateInDatabase("config", { value: "1" }, { name: ADVANCED_UI_CONFIG });
            await skinConfig.goto();

            tester.isGoingTo("preview the large container drop shadow");
            await tester.common.selectOption(skinConfig.getField("skin_container_drop_shadow"), "Large");

            await expect
                .poll(async () => {
                    const containerShadow = await page.evaluate(
                        getCssCustomProperty,
                        "--sbx-config-container-drop-shadow",
                    );
                    const largeShadow = await page.evaluate(getCssCustomProperty, "--sbx-shadow-l");
                    return containerShadow === largeShadow;
                })
                .toBe(true);
        } finally {
            await db.updateInDatabase("config", { value: "0" }, { name: ADVANCED_UI_CONFIG });
        }
    });

    test("previews custom CSS", async ({ tester, page, db }) => {
        const skinConfig = new SkinConfig(page);

        try {
            await db.updateInDatabase("config", { value: "1" }, { name: ADVANCED_UI_CONFIG });
            await skinConfig.goto();

            tester.isGoingTo("preview a green page background from Custom CSS");
            await skinConfig.setCustomCss("body { background: green; }");
            await expect.poll(() => skinConfig.getBodyBackgroundColour()).toBe("rgb(0, 128, 0)");
        } finally {
            await db.updateInDatabase("config", { value: "0" }, { name: ADVANCED_UI_CONFIG });
        }
    });

    test("undoes custom CSS changes", async ({ tester, page, db }) => {
        const skinConfig = new SkinConfig(page);

        try {
            await db.updateInDatabase("config", { value: "1" }, { name: ADVANCED_UI_CONFIG });
            await skinConfig.goto();

            tester.isGoingTo("preview a green page background from Custom CSS");
            await skinConfig.setCustomCss("body { background: green; }");
            await expect.poll(() => skinConfig.getBodyBackgroundColour()).toBe("rgb(0, 128, 0)");

            tester.isGoingTo("undo the Custom CSS preview");
            await skinConfig.undo();
            await expect(skinConfig.getField("skin_sonar_custom_css")).toHaveValue("");
            await expect.poll(() => skinConfig.getBodyBackgroundColour()).not.toBe("rgb(0, 128, 0)");
        } finally {
            await db.updateInDatabase("config", { value: "0" }, { name: ADVANCED_UI_CONFIG });
        }
    });

    test("persists Accent styling on Pantry buttons", async ({ tester, page, db }) => {
        const skinConfig = new SkinConfig(page);
        const configName = "skin_colour_accent";
        const foregroundConfigName = "skin_colour_menu_foreground";
        const accentColour = "rgb(2, 99, 51)";
        const originalValue = (await db.grabFromDatabase("config", "value", { name: configName })) as string;
        const originalForegroundValue = (await db.grabFromDatabase("config", "value", {
            name: foregroundConfigName,
        })) as string;

        try {
            await db.updateInDatabase("config", { value: "1" }, { name: ADVANCED_UI_CONFIG });
            await db.updateInDatabase("config", { value: "rgb(255, 255, 255)" }, { name: foregroundConfigName });
            await skinConfig.goto();

            tester.isGoingTo("save a green Accent colour");
            await tester.common.setColour(skinConfig.getField(configName), accentColour);
            await skinConfig.save();
            await expect(skinConfig.getSaveSuccessToast()).toBeVisible();

            tester.isGoingTo("confirm the Accent colour persists on Pantry buttons");
            await skinConfig.gotoPantryButtons();
            await expect(skinConfig.getPantrySubmitButton()).toHaveCSS("background-color", accentColour);
            await expect(skinConfig.getPantryLoadMoreButton()).toHaveCSS("border-color", accentColour);
        } finally {
            try {
                await Promise.all([
                    db.updateInDatabase("config", { value: originalValue }, { name: configName }),
                    db.updateInDatabase("config", { value: originalForegroundValue }, { name: foregroundConfigName }),
                ]);
            } finally {
                await db.updateInDatabase("config", { value: "0" }, { name: ADVANCED_UI_CONFIG });
            }
        }
    });
});

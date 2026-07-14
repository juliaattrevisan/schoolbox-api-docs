/**
 * Tests Sonar UI Skin Config validation, live previews, undo, and persisted Accent styling.
 * 1. Provision sales.tar.gz data
 * 2. Execute DOCKER=1 npx playwright test skinConfig.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";
import SkinConfig from "@schoolboxTest/playwright/support/page/admin/design/SkinConfig";

const ADVANCED_UI_CONFIG = "advanced_ui_enabled";

/**
 * Read a config value for later restoration.
 * @param db Database helper.
 * @param name Config name.
 * @returns Stored config value.
 */
async function getConfigValue(db: DatabaseHelper, name: string): Promise<string> {
    const [value] = await db.grabColumnFromDatabase("config", "value", { name });
    if (typeof value !== "string") {
        throw new Error(`Config ${name} does not have a string value`);
    }
    return value;
}

/**
 * Update a config value used by the current test.
 * @param db Database helper.
 * @param name Config name.
 * @param value Config value.
 */
async function setConfigValue(db: DatabaseHelper, name: string, value: string): Promise<void> {
    await db.updateInDatabase("config", { value }, { name });
}

test.describe("Skin Config", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("requires valid colour contrast before saving", async ({ tester, page, db }) => {
        const skinConfig = new SkinConfig(page);
        const configName = "skin_colour_menu_foreground";
        const originalValue = await getConfigValue(db, configName);

        try {
            await setConfigValue(db, ADVANCED_UI_CONFIG, "1");
            await skinConfig.goto();

            tester.isGoingTo("confirm invalid Top Menu colour contrast prevents saving");
            await expect(skinConfig.getValidationError(configName)).toContainText("Insufficient contrast");
            await skinConfig.save();
            await expect(skinConfig.getSaveSuccessToast()).not.toBeVisible();

            tester.isGoingTo("change Top Menu Foreground to white and save");
            await skinConfig.setColour(configName, "rgb(255, 255, 255)");
            await expect(skinConfig.getValidationError(configName)).not.toBeVisible();
            await skinConfig.save();
            await expect(skinConfig.getSaveSuccessToast()).toBeVisible();
        } finally {
            try {
                await setConfigValue(db, configName, originalValue);
            } finally {
                await setConfigValue(db, ADVANCED_UI_CONFIG, "0");
            }
        }
    });

    test("previews container and field corner radii", async ({ tester, page, db }) => {
        const skinConfig = new SkinConfig(page);

        try {
            await setConfigValue(db, ADVANCED_UI_CONFIG, "1");
            await skinConfig.goto();

            tester.isGoingTo("preview no container radius and a large field radius");
            await skinConfig.selectOption("skin_container_corner_radius", "None");
            await skinConfig.selectOption("skin_field_corner_radius", "Large");

            await expect.poll(() => skinConfig.getCssCustomProperty("--sbx-config-container-corner-radius")).toBe("0");
            await expect
                .poll(() => skinConfig.getCssCustomProperty("--sbx-config-field-corner-radius"))
                .toBe("0.75rem");
        } finally {
            await setConfigValue(db, ADVANCED_UI_CONFIG, "0");
        }
    });

    test("previews a large container drop shadow", async ({ tester, page, db }) => {
        const skinConfig = new SkinConfig(page);

        try {
            await setConfigValue(db, ADVANCED_UI_CONFIG, "1");
            await skinConfig.goto();

            tester.isGoingTo("preview the large container drop shadow");
            await skinConfig.selectOption("skin_container_drop_shadow", "Large");

            await expect
                .poll(async () => {
                    const containerShadow = await skinConfig.getCssCustomProperty("--sbx-config-container-drop-shadow");
                    const largeShadow = await skinConfig.getCssCustomProperty("--sbx-shadow-l");
                    return containerShadow === largeShadow;
                })
                .toBe(true);
        } finally {
            await setConfigValue(db, ADVANCED_UI_CONFIG, "0");
        }
    });

    test("previews custom CSS", async ({ tester, page, db }) => {
        const skinConfig = new SkinConfig(page);

        try {
            await setConfigValue(db, ADVANCED_UI_CONFIG, "1");
            await skinConfig.goto();

            tester.isGoingTo("preview a green page background from Custom CSS");
            await skinConfig.setCustomCss("body { background: green; }");
            await expect.poll(() => skinConfig.getBodyBackgroundColour()).toBe("rgb(0, 128, 0)");
        } finally {
            await setConfigValue(db, ADVANCED_UI_CONFIG, "0");
        }
    });

    test("undoes custom CSS changes", async ({ tester, page, db }) => {
        const skinConfig = new SkinConfig(page);

        try {
            await setConfigValue(db, ADVANCED_UI_CONFIG, "1");
            await skinConfig.goto();

            tester.isGoingTo("preview a green page background from Custom CSS");
            await skinConfig.setCustomCss("body { background: green; }");
            await expect.poll(() => skinConfig.getBodyBackgroundColour()).toBe("rgb(0, 128, 0)");

            tester.isGoingTo("undo the Custom CSS preview");
            await skinConfig.undo();
            await expect(skinConfig.getField("skin_sonar_custom_css")).toHaveValue("");
            await expect.poll(() => skinConfig.getBodyBackgroundColour()).not.toBe("rgb(0, 128, 0)");
        } finally {
            await setConfigValue(db, ADVANCED_UI_CONFIG, "0");
        }
    });

    test("persists Accent styling on Pantry buttons", async ({ tester, page, db }) => {
        const skinConfig = new SkinConfig(page);
        const configName = "skin_colour_accent";
        const foregroundConfigName = "skin_colour_menu_foreground";
        const accentColour = "rgb(2, 99, 51)";
        const originalValue = await getConfigValue(db, configName);
        const originalForegroundValue = await getConfigValue(db, foregroundConfigName);

        try {
            await setConfigValue(db, ADVANCED_UI_CONFIG, "1");
            await setConfigValue(db, foregroundConfigName, "rgb(255, 255, 255)");
            await skinConfig.goto();

            tester.isGoingTo("save a green Accent colour");
            await skinConfig.setColour(configName, accentColour);
            await skinConfig.save();
            await expect(skinConfig.getSaveSuccessToast()).toBeVisible();

            tester.isGoingTo("confirm the Accent colour persists on Pantry buttons");
            await skinConfig.gotoPantryButtons();
            await expect(skinConfig.getPantrySubmitButton()).toHaveCSS("background-color", accentColour);
            await expect(skinConfig.getPantryLoadMoreButton()).toHaveCSS("border-color", accentColour);
        } finally {
            try {
                await Promise.all([
                    setConfigValue(db, configName, originalValue),
                    setConfigValue(db, foregroundConfigName, originalForegroundValue),
                ]);
            } finally {
                await setConfigValue(db, ADVANCED_UI_CONFIG, "0");
            }
        }
    });
});

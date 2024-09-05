import type { FontColorConfig, FontFamilyConfig, FontSizeConfig } from "@ckeditor/ckeditor5-font";
import { fontColourSwatches, fontBackgroundColourSwatches } from "./colourSettings";

interface FontSettingsConfig {
    fontFamily: FontFamilyConfig;
    fontSize: FontSizeConfig;
    fontColor: FontColorConfig;
    fontBackgroundColor: FontColorConfig;
}

/**
 * Returns the settings for the font options
 * @returns - the font settings
 */
export function getFontSettings(): FontSettingsConfig {
    return {
        fontFamily: {
            options: [
                "default",
                "Arial, Helvetica, sans-serif",
                "Comic Sans, Comic Sans MS, Chalkboard, ChalkboardSE-Regular, sans-serif",
                "Courier New, Courier, monospace",
                "Georgia, serif",
                "Lucida Sans Unicode, Lucida Grande, Helvetica Neue, sans-serif",
                "Tahoma, Geneva, sans-serif",
                "Times New Roman, Times, serif",
                "Trebuchet MS, Helvetica, sans-serif",
                "Verdana, Geneva, sans-serif",
            ],
            supportAllValues: true,
        },
        fontSize: {
            options: [
                {
                    title: "Tiny",
                    model: "0.625rem",
                },
                {
                    title: "Small",
                    model: "0.75rem",
                },
                "default",
                {
                    title: "Large",
                    model: "1rem",
                },
                {
                    title: "Larger",
                    model: "1.0625rem",
                },
                {
                    title: "Largest",
                    model: "1.125rem",
                },
            ],
            supportAllValues: true,
        },
        fontColor: {
            colors: fontColourSwatches,
        },
        fontBackgroundColor: {
            colors: fontBackgroundColourSwatches,
        },
    };
}

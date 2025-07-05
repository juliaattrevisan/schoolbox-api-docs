/**
 * Returns the default config setup for a CKEditor 5 instance
 * for use in the Editor.create() method
 */

import { type EditorConfig } from "ckeditor5";
import { getPlugins } from "@js/editor/plugin/editorPlugins";
import { getToolbar } from "@js/editor/toolbar/editorToolbar";
import { getLinkSettings } from "@js/editor/settings/linkSettings";
import { getFontSettings } from "@js/editor/settings/fontSettings";
import { getStyleSettings } from "@js/editor/settings/styleSettings";
import { getHeadingSettings } from "@js/editor/settings/headingSettings";
import { getImageSettings } from "@js/editor/settings/imageSettings";
import { getTableSettings } from "@js/editor/settings/tableSettings";
import { getMediaEmbedSettings } from "@js/editor/settings/mediaEmbedSettings";
import { getTokensSettings } from "@js/editor/settings/tokensSettings";
import { getWordCountSettings } from "@js/editor/settings/wordCountSettings";
import { getHTMLSupportSettings } from "@js/editor/settings/htmlSupportSettings";
import { getAISettings } from "@js/editor/settings/aiSettings";
import { getFullscreenSettings } from "@js/editor/settings/fullScreenSettings";
import { getSpecialCharactersSettings } from "@js/editor/settings/specialCharactersSettings";
import { getTemplateSettings } from "@js/editor/settings/templateSettings";
import { type SbxEditorConfig } from "@js/editor/types/editorTypes";

/**
 * Return the default configuration
 * @param config - the default config
 * @returns - refer to https://ckeditor.com/docs/ckeditor5/latest/api/module_core_editor_editorconfig-EditorConfig.html
 */
export function getConfig(config: SbxEditorConfig): EditorConfig & { sbConfig: SbxEditorConfig } {
    return {
        ...getPlugins(config),
        licenseKey: window.sbProps.ckeditor5LicenseKey,
        toolbar: getToolbar(config),
        ...getLinkSettings(),
        ...getFontSettings(),
        ...getStyleSettings(),
        ...getHeadingSettings(),
        ...getImageSettings(),
        ...getTableSettings(),
        ...getMediaEmbedSettings(),
        ...getTokensSettings(config),
        ...getWordCountSettings(),
        ...getHTMLSupportSettings(),
        ...getAISettings(window.sbProps.openAIModel),
        ...getFullscreenSettings(),
        ...getSpecialCharactersSettings(),
        emoji: {
            definitionsUrl: "cdn", // use the default CDN for emoji definitions
        },
        ...getTemplateSettings(config),
        sbConfig: { ...config }, // to be able to access our editor config from within the editor,
    };
}

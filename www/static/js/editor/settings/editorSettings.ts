/**
 * Returns all settings for use within the editor
 */

import { type EditorConfig } from "ckeditor5";
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
 * Return all settings
 * @param config - the default config
 * @returns - refer to https://ckeditor.com/docs/ckeditor5/latest/api/module_core_editor_editorconfig-EditorConfig.html
 */
export function getSettings(config: SbxEditorConfig): EditorConfig {
    return {
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
        ...getAISettings(window.sbProps.aiFeatures.chat.model),
        ...getFullscreenSettings(),
        ...getSpecialCharactersSettings(),
        ...getTemplateSettings(config),
        emoji: {
            definitionsUrl: "cdn", // use the default CDN for emoji definitions
        },
    };
}

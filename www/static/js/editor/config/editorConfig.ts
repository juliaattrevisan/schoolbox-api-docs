/**
 * Returns the default config setup for a CKEditor 5 instance
 * for use in the Editor.create() method
 */

import { getCorePlugins } from "@js/editor/plugin/corePlugins";
import { getImagePlugins } from "@js/editor/plugin/imagePlugins";
import { getTablePlugins } from "@js/editor/plugin/tablePlugins";
import { getTokensPlugins } from "@js/editor/plugin/tokensPlugins";
import { getEmojiPlugins } from "@js/editor/plugin/emojiPlugins";
import {
    Heading,
    HeadingButtonsUI,
    Paragraph,
    ParagraphButtonUI,
    Style,
    Subscript,
    Superscript,
    Font,
    RemoveFormat,
    MediaEmbed,
    HorizontalLine,
    Alignment,
    FindAndReplace,
    WordCount,
    Fullscreen,
    Bookmark,
    type EditorConfig,
} from "ckeditor5";
import { AIAssistant, OpenAITextAdapter, SourceEditingEnhanced } from "ckeditor5-premium-features";
import UploadAdapterPlugin from "@js/editor/plugin/uploadAdapter/UploadAdapterPlugin";
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
import { EditorToolbar, type SbxEditorConfig } from "@js/editor/types/editorTypes";
import Eqneditor5 from "@js/editor/plugin/eqnEditor/eqneditor5";
import Resource from "@js/editor/plugin/resource/Resource";
import UITweaks from "@js/editor/plugin/ui/UITweaks";
import { getSpecialCharactersPlugins } from "../plugin/specialCharactersPlugins";

/**
 * Return the default configuration
 * @param config - the default config
 * @returns - refer to https://ckeditor.com/docs/ckeditor5/latest/api/module_core_editor_editorconfig-EditorConfig.html
 */
export function getConfig(config: SbxEditorConfig): EditorConfig & { sbConfig: SbxEditorConfig } {
    return {
        plugins: [
            // list all the plugins we'll use in the default editor
            ...getCorePlugins(),
            ...getImagePlugins(),
            ...getTablePlugins(),
            ...getEmojiPlugins(),
            ...getTokensPlugins(config),
            ...getSpecialCharactersPlugins(),
            Heading,
            Paragraph,
            HeadingButtonsUI,
            ParagraphButtonUI,
            Style,
            Subscript,
            Superscript,
            Font,
            RemoveFormat,
            MediaEmbed,
            HorizontalLine,
            Alignment,
            FindAndReplace,
            SourceEditingEnhanced,
            WordCount,
            Eqneditor5,
            Resource,
            UITweaks,
            AIAssistant,
            OpenAITextAdapter,
            Fullscreen,
            Bookmark,
        ],
        extraPlugins: config.toolbar !== EditorToolbar.SIMPLE ? [UploadAdapterPlugin] : [],
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
        sbConfig: { ...config }, // to be able to access our editor config from within the editor,
    };
}

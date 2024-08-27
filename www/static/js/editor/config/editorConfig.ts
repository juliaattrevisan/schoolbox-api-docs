/**
 * Returns the default config setup for a CKEditor 5 instance
 * for use in the Editor.create() method
 */

import { getCorePlugins } from "@js/editor/plugin/corePlugins";
import { getImagePlugins } from "@js/editor/plugin/imagePlugins";
import { getTablePlugins } from "@js/editor/plugin/tablePlugins";
import { getTokensPlugins } from "@js/editor/plugin/tokensPlugins";
import { getEmojiPlugins } from "@js/editor/plugin/emojiPlugins";
import { Heading, HeadingButtonsUI } from "@ckeditor/ckeditor5-heading";
import { Paragraph, ParagraphButtonUI } from "@ckeditor/ckeditor5-paragraph";
import { Style } from "@ckeditor/ckeditor5-style";
import { Subscript, Superscript } from "@ckeditor/ckeditor5-basic-styles";
import { Font } from "@ckeditor/ckeditor5-font";
import { RemoveFormat } from "@ckeditor/ckeditor5-remove-format";
import { MediaEmbed } from "@ckeditor/ckeditor5-media-embed";
import { SpecialCharacters, SpecialCharactersEssentials } from "@ckeditor/ckeditor5-special-characters";
import { HorizontalLine } from "@ckeditor/ckeditor5-horizontal-line";
import { Alignment } from "@ckeditor/ckeditor5-alignment";
import { FindAndReplace } from "@ckeditor/ckeditor5-find-and-replace";
import { SourceEditing } from "@ckeditor/ckeditor5-source-editing";
import { WordCount } from "@ckeditor/ckeditor5-word-count";
import { AIAssistant, OpenAITextAdapter } from "@ckeditor/ckeditor5-ai";
import type { EditorConfig } from "@ckeditor/ckeditor5-core";
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
import { SbxEditorConfig } from "@js/editor/types/editorTypes";
import Eqneditor5 from "@js/editor/plugin/eqnEditor/eqneditor5";
import Resource from "@js/editor/plugin/resource/Resource";
import UITweaks from "@js/editor/plugin/ui/UITweaks";

/**
 * Return the default configuration
 * @param { SbxEditorConfig } config - the default config
 * @returns { EditorConfig & { sbConfig: SbxEditorConfig } } - refer to https://ckeditor.com/docs/ckeditor5/latest/api/module_core_editor_editorconfig-EditorConfig.html
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
            SpecialCharacters,
            SpecialCharactersEssentials,
            HorizontalLine,
            Alignment,
            FindAndReplace,
            SourceEditing,
            WordCount,
            Eqneditor5,
            Resource,
            UITweaks,
            AIAssistant,
            OpenAITextAdapter,
        ],
        extraPlugins: [UploadAdapterPlugin],
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
        sbConfig: { ...config }, // to be able to access our editor config from within the editor,
    };
}

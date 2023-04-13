/**
 * Returns the default config setup for a CKEditor 5 instance
 * for use in the Editor.create() method
 */

import { getCorePlugins } from "@js/editor/plugin/corePlugins";
import { getImagePlugins } from "@js/editor/plugin/imagePlugins";
import { getTablePlugins } from "@js/editor/plugin/tablePlugins";
import { getTokensPlugins } from "@js/editor/plugin/tokensPlugins";
import { getEmojiPlugins } from "@js/editor/plugin/emojiPlugins";
import Heading from "@ckeditor/ckeditor5-heading/src/heading";
import Paragraph from "@ckeditor/ckeditor5-paragraph/src/paragraph";
import HeadingButtonsUI from "@ckeditor/ckeditor5-heading/src/headingbuttonsui";
import ParagraphButtonUI from "@ckeditor/ckeditor5-paragraph/src/paragraphbuttonui";
import Style from "@ckeditor/ckeditor5-style/src/style";
import Subscript from "@ckeditor/ckeditor5-basic-styles/src/subscript";
import Superscript from "@ckeditor/ckeditor5-basic-styles/src/superscript";
import Font from "@ckeditor/ckeditor5-font/src/font";
import RemoveFormat from "@ckeditor/ckeditor5-remove-format/src/removeformat";
import SimpleUploadAdapter from "@ckeditor/ckeditor5-upload/src/adapters/simpleuploadadapter";
import MediaEmbed from "@ckeditor/ckeditor5-media-embed/src/mediaembed";
import SpecialCharacters from "@ckeditor/ckeditor5-special-characters/src/specialcharacters";
import SpecialCharactersEssentials from "@ckeditor/ckeditor5-special-characters/src/specialcharactersessentials";
import HorizontalLine from "@ckeditor/ckeditor5-horizontal-line/src/horizontalline";
import Alignment from "@ckeditor/ckeditor5-alignment/src/alignment";
import FindAndReplace from "@ckeditor/ckeditor5-find-and-replace/src/findandreplace";
import SourceEditing from "@ckeditor/ckeditor5-source-editing/src/sourceediting";
import WordCount from "@ckeditor/ckeditor5-word-count/src/wordcount";
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
import { EditorConfig } from "@js/editor/types/editorTypes";
import Eqneditor5 from "@js/editor/plugin/eqnEditor/eqneditor5";
import Resource from "@js/editor/plugin/resource/Resource";
import Blank from "@js/editor/plugin/blank/Blank";

/**
 * Return the default configuration
 *
 * @param { EditorConfig } config - the default config
 * @returns { EditorConfig } - refer to https://ckeditor.com/docs/ckeditor5/latest/api/module_core_editor_editorconfig-EditorConfig.html
 */
export function getConfig(config: EditorConfig) {
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
            SimpleUploadAdapter,
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
            Blank,
        ],
        extraPlugins: [UploadAdapterPlugin],
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
        sbConfig: { ...config }, // to be able to access our editor config from within the editor,
    };
}

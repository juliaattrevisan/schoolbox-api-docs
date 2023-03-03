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
import TodoList from "@ckeditor/ckeditor5-list/src/todolist";
import Alignment from "@ckeditor/ckeditor5-alignment/src/alignment";
import FindAndReplace from "@ckeditor/ckeditor5-find-and-replace/src/findandreplace";
import SourceEditing from "@ckeditor/ckeditor5-source-editing/src/sourceediting";
import WordCount from "@ckeditor/ckeditor5-word-count/src/wordcount";
import UploadAdapterPlugin from "@js/editor/plugin/UploadAdapterPlugin";
import { getMediaEmbedSettings } from "@js/editor/settings/mediaEmbedSettings";
import { getTokensSettings } from "@js/editor/settings/tokensSettings";
import { getStyleSettings } from "@js/editor/settings/styleSettings";
import { getImageSettings } from "@js/editor/settings/imageSettings";
import { getTableSettings } from "@js/editor/settings/tableSettings";
import { getHeadingSettings } from "@js/editor/settings/headingSettings";
import { getFontSettings } from "@js/editor/settings/fontSettings";
import { EditorDefaultConfig } from "@js/editor/types/editorTypes";
import Resource from "@js/editor/plugin/resource/Resource";

/**
 * Return the default configuration
 *
 * @param { EditorDefaultConfig } config - the default config
 * @returns { EditorConfig } - refer to https://ckeditor.com/docs/ckeditor5/latest/api/module_core_editor_editorconfig-EditorConfig.html
 */
export function getDefault(config: EditorDefaultConfig) {
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
            TodoList,
            Alignment,
            FindAndReplace,
            SourceEditing,
            WordCount,
            Resource,
        ],
        extraPlugins: [UploadAdapterPlugin],
        toolbar: [
            // what displays in the default toolbar in order, are either single buttons or groups via objects
            "bold",
            "italic",
            {
                label: "Styles",
                icon: "text",
                items: [
                    "underline",
                    "strikethrough",
                    "subscript",
                    "superscript",
                    "blockQuote",
                    "fontColor",
                    "fontBackgroundColor",
                    "fontSize",
                    "fontFamily",
                ],
            },
            "removeFormat",
            "|",
            "style",
            "|",
            "heading",
            "|",
            "link",
            "|",
            {
                label: "Insert",
                icon: "plus",
                items: [
                    "insertImage",
                    "mediaEmbed",
                    "resource",
                    "insertTable",
                    "specialCharacters",
                    "emoji",
                    "horizontalLine",
                ],
            },
            "|",
            {
                label: "Lists",
                icon: "threeVerticalDots",
                items: ["numberedList", "bulletedList", "todoList"],
            },
            "|",
            "alignment",
            "|",
            "undo",
            "redo",
            "|",
            "findAndReplace",
            "|",
            "sourceEditing",
        ],
        ...getFontSettings(),
        ...getStyleSettings(),
        ...getHeadingSettings(),
        ...getImageSettings(),
        ...getTableSettings(),
        ...getMediaEmbedSettings(),
        ...getTokensSettings(config),
    };
}

/**
 * Returns the default config setup for a CKEditor 5 instance
 * for use in the Editor.create() method
 */

import Essentials from "@ckeditor/ckeditor5-essentials/src/essentials";
import Heading from "@ckeditor/ckeditor5-heading/src/heading";
import Paragraph from "@ckeditor/ckeditor5-paragraph/src/paragraph";
import HeadingButtonsUI from "@ckeditor/ckeditor5-heading/src/headingbuttonsui";
import ParagraphButtonUI from "@ckeditor/ckeditor5-paragraph/src/paragraphbuttonui";
import GeneralHtmlSupport from "@ckeditor/ckeditor5-html-support/src/generalhtmlsupport";
import Style from "@ckeditor/ckeditor5-style/src/style";
import Bold from "@ckeditor/ckeditor5-basic-styles/src/bold";
import Italic from "@ckeditor/ckeditor5-basic-styles/src/italic";
import Underline from "@ckeditor/ckeditor5-basic-styles/src/underline";
import Strikethrough from "@ckeditor/ckeditor5-basic-styles/src/strikethrough";
import Subscript from "@ckeditor/ckeditor5-basic-styles/src/subscript";
import Superscript from "@ckeditor/ckeditor5-basic-styles/src/superscript";
import BlockQuote from "@ckeditor/ckeditor5-block-quote/src/blockquote";
import Font from "@ckeditor/ckeditor5-font/src/font";
import RemoveFormat from "@ckeditor/ckeditor5-remove-format/src/removeformat";
import Image from "@ckeditor/ckeditor5-image/src/image";
import ImageToolbar from "@ckeditor/ckeditor5-image/src/imagetoolbar";
import ImageCaption from "@ckeditor/ckeditor5-image/src/imagecaption";
import ImageStyle from "@ckeditor/ckeditor5-image/src/imagestyle";
import ImageResize from "@ckeditor/ckeditor5-image/src/imageresize";
import LinkImage from "@ckeditor/ckeditor5-link/src/linkimage";
import ImageInsert from "@ckeditor/ckeditor5-image/src/imageinsert";
import SimpleUploadAdapter from "@ckeditor/ckeditor5-upload/src/adapters/simpleuploadadapter";
import MediaEmbed from "@ckeditor/ckeditor5-media-embed/src/mediaembed";
import Table from "@ckeditor/ckeditor5-table/src/table";
import SpecialCharacters from "@ckeditor/ckeditor5-special-characters/src/specialcharacters";
import SpecialCharactersEssentials from "@ckeditor/ckeditor5-special-characters/src/specialcharactersessentials";
import {
    Emoji,
    EmojiActivity,
    EmojiFlags,
    EmojiFood,
    EmojiNature,
    EmojiObjects,
    EmojiPeople,
    EmojiPlaces,
    EmojiSymbols,
} from "@phudak/ckeditor5-emoji/src";
import HorizontalLine from "@ckeditor/ckeditor5-horizontal-line/src/horizontalline";
import TableToolbar from "@ckeditor/ckeditor5-table/src/tabletoolbar";
import TableProperties from "@ckeditor/ckeditor5-table/src/tableproperties";
import TableCellProperties from "@ckeditor/ckeditor5-table/src/tablecellproperties";
import TableColumnResize from "@ckeditor/ckeditor5-table/src/tablecolumnresize";
import TableCaption from "@ckeditor/ckeditor5-table/src/tablecaption";
import Link from "@ckeditor/ckeditor5-link/src/link";
import List from "@ckeditor/ckeditor5-list/src/list";
import TodoList from "@ckeditor/ckeditor5-list/src/todolist";
import Alignment from "@ckeditor/ckeditor5-alignment/src/alignment";
import FindAndReplace from "@ckeditor/ckeditor5-find-and-replace/src/findandreplace";
import SourceEditing from "@ckeditor/ckeditor5-source-editing/src/sourceediting";
import WordCount from "@ckeditor/ckeditor5-word-count/src/wordcount";
import UploadAdapter from "../plugin/UploadAdapter";
import { getDefaultColors } from "@js/editor/config/configDefaultColors";
import { getTokensPlugins } from "@js/editor/plugin/tokensPlugins";
import { getMediaEmbedSettings } from "@js/editor/settings/mediaEmbedSettings";
import { getTokensSettings } from "@js/editor/settings/tokensSettings";

import SchoolboxResource from "../plugin/schoolboxResource/SchoolboxResource";

/**
 * Add our custom upload adapter
 *
 * @param { CKEditor } editor - the CKEditor instance
 */
function UploadAdapterPlugin(editor) {
    editor.plugins.get("FileRepository").createUploadAdapter = (loader) => {
        return new UploadAdapter(loader);
    };
}

export interface EditorDefaultConfig {
    targetElements: string[]; // the elements to target for the editor
    hasTokens?: boolean; // whether or not to enable the tokens plugin
}

/**
 * Return the default configuration
 *
 * @param { EditorDefaultConfig } config - the default config
 * @returns { EditorConfig } - refer to https://ckeditor.com/docs/ckeditor5/latest/api/module_core_editor_editorconfig-EditorConfig.html
 */
export function getDefault(config: EditorDefaultConfig) {
    return {
        plugins: [
            // list all the plugins we'll use in the editor
            Essentials,
            Heading,
            Paragraph,
            HeadingButtonsUI,
            ParagraphButtonUI,
            GeneralHtmlSupport,
            Style,
            Bold,
            Italic,
            Underline,
            Strikethrough,
            Subscript,
            Superscript,
            BlockQuote,
            Font,
            RemoveFormat,
            Image,
            ImageToolbar,
            ImageCaption,
            ImageStyle,
            ImageResize,
            LinkImage,
            ImageInsert,
            SimpleUploadAdapter,
            MediaEmbed,
            Table,
            TableToolbar,
            TableProperties,
            TableCellProperties,
            TableColumnResize,
            TableCaption,
            SpecialCharacters,
            SpecialCharactersEssentials,
            Emoji,
            EmojiPeople,
            EmojiNature,
            EmojiFood,
            EmojiPlaces,
            EmojiActivity,
            EmojiObjects,
            EmojiSymbols,
            EmojiFlags,
            HorizontalLine,
            Link,
            List,
            TodoList,
            Alignment,
            FindAndReplace,
            SourceEditing,
            WordCount,
            ...getTokensPlugins(config),
            Timestamp,
            Abbreviation,
            SchoolboxResource,
        ],
        extraPlugins: [UploadAdapterPlugin],
        toolbar: [
            // what displays in the toolbar in order, are either single buttons or groups via objects
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
                items: ["insertImage", "mediaEmbed", "insertTable", "specialCharacters", "emoji", "horizontalLine"],
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
            "schoolboxResource",
        ],
        style: {
            // the list of styles provided: https://ckeditor.com/docs/ckeditor5/latest/features/style.html
            definitions: [
                {
                    name: "Small",
                    element: "small",
                    classes: [""],
                },
                {
                    name: "Typewriter",
                    element: "tt",
                    classes: [""],
                },
                /* {
                    name: "Computer Code",
                    element: "code",
                    classes: [""],
                }, */
                {
                    name: "Highlighted Note",
                    element: "span",
                    classes: ["highlighted-note"],
                },
                {
                    name: "Banner",
                    element: "p",
                    classes: ["s-banner"],
                },
                {
                    name: "Info Callout",
                    element: "p",
                    classes: ["s-callout", "s-callout--info"],
                },
                {
                    name: "Warning Callout",
                    element: "p",
                    classes: ["s-callout", "s-callout--warning"],
                },
                {
                    name: "Success Callout",
                    element: "p",
                    classes: ["s-callout", "s-callout--success"],
                },
                {
                    name: "Target Callout",
                    element: "p",
                    classes: ["s-callout", "s-callout--target"],
                },
                {
                    name: "Question Callout",
                    element: "p",
                    classes: ["s-callout", "s-callout--question"],
                },
                {
                    name: "Sticky Note",
                    element: "p",
                    classes: ["s-sticky-note"],
                },
                {
                    name: "Construction",
                    element: "p",
                    classes: ["s-construction"],
                },
                { name: "Code block", element: "p", classes: ["s-code-block"] },
            ],
        },
        heading: {
            // the list of headings provided: https://ckeditor.com/docs/ckeditor5/latest/features/headings.html
            options: [
                { model: "paragraph", title: "Paragraph", class: "ck-heading_paragraph" },
                { model: "heading2", view: "h2", title: "Heading 2", class: "ck-heading_heading2" },
                { model: "heading3", view: "h3", title: "Heading 3", class: "ck-heading_heading3" },
                { model: "heading4", view: "h4", title: "Heading 4", class: "ck-heading_heading4" },
                { model: "heading5", view: "h5", title: "Heading 5", class: "ck-heading_heading5" },
            ],
        },
        image: {
            // image setup: https://ckeditor.com/docs/ckeditor5/latest/features/images/images-overview.html
            toolbar: [
                "imageTextAlternative",
                "toggleImageCaption",
                "linkImage",
                "|",
                "imageStyle:inline",
                {
                    name: "imageStyle:wrapText",
                    title: "Wrap text",
                    items: ["imageStyle:alignLeft", "imageStyle:alignRight"],
                    defaultItem: "imageStyle:alignLeft",
                },
                {
                    name: "imageStyle:breakText",
                    title: "Break text",
                    items: ["imageStyle:alignBlockLeft", "imageStyle:block", "imageStyle:alignBlockRight"],
                    defaultItem: "imageStyle:block",
                },
                "imageStyle:side",
                "|",
                "resizeImage",
            ],
        },
        table: {
            // the table controls provided: https://ckeditor.com/docs/ckeditor5/latest/features/table.html
            contentToolbar: [
                "tableColumn",
                "tableRow",
                "mergeTableCells",
                "tableProperties",
                "tableCellProperties",
                "toggleTableCaption",
            ],
        },
        fontColor: {
            columns: 12,
            colors: getDefaultColors(),
        },
        fontBackgroundColor: {
            columns: 12,
            colors: getDefaultColors(),
        },
        fontFamily: {
            options: [
                "default",
                "Arial, Helvetica, sans-serif",
                "Comic Sans MS, Comic Sans, cursive",
                "Courier New, Courier, monospace",
                "Georgia, serif",
                "Lucida Sans Unicode, Lucida Grande, sans-serif",
                "Tahoma, Geneva, sans-serif",
                "Times New Roman, Times, serif",
                "Trebuchet MS, Helvetica, sans-serif",
                "Verdana, Geneva, sans-serif",
            ],
        },
        ...getMediaEmbedSettings(),
        ...getTokensSettings(config),
    };
}

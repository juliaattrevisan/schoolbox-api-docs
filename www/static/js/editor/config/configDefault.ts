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
import CodeBlock from "@ckeditor/ckeditor5-code-block/src/codeblock";
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

const IFRAME_SRC = "//cdn.iframe.ly/api/iframe";
const API_KEY = "94aa8fc28ac1089a2dfb28";

/**
 * Return the default configuration
 *
 * @returns { EditorConfig } - refer to https://ckeditor.com/docs/ckeditor5/latest/api/module_core_editor_editorconfig-EditorConfig.html
 */
export function getDefault() {
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
            CodeBlock,
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
                items: [
                    "insertImage",
                    "mediaEmbed",
                    "insertTable",
                    "codeblock",
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
                {
                    name: "Computer Code",
                    element: "code",
                    classes: [""],
                },
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
                    name: "Banner 2",
                    element: "p",
                    classes: ["s-banner-2"],
                },
                {
                    name: "Banner 3",
                    element: "p",
                    classes: ["s-banner-3"],
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
                {
                    name: "Construction 2",
                    element: "p",
                    classes: ["s-construction-2"],
                },
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
        // Configure 'mediaEmbed' with Iframely previews.
        mediaEmbed: {
            // Previews are always enabled if there’s a provider for a URL (below regex catches all URLs)
            // By default `previewsInData` are disabled, but let’s set it to `false` explicitely to be sure
            previewsInData: false,
            providers: [
                {
                    name: "iframely previews", // hint: this is just for previews. Get actual HTML codes by making API calls from your CMS
                    url: /.+/, // Match all URLs or just the ones you need
                    html: (match: string) => {
                        const url = match[0];
                        const iframeUrl = `${IFRAME_SRC}?app=1&api_key=${API_KEY}&url=${encodeURIComponent(url)}`;
                        // alternatively, use &key= instead of &api_key with the MD5 hash of your api_key
                        // more about it: https://iframely.com/docs/allow-origins
                        return (
                            // If you need, set maxwidth and other styles for 'iframely-embed' class - it's yours to customize
                            `<div class="iframely-embed">
                                <div class="iframely-responsive">
                                    <iframe src="${iframeUrl}" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen>
                                    </iframe>
                                </div>
                            </div>`
                        );
                    },
                },
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
                "Courier New, Courier, monospace",
                "Georgia, serif",
                "Lucida Sans Unicode, Lucida Grande, sans-serif",
                "Tahoma, Geneva, sans-serif",
                "Times New Roman, Times, serif",
                "Trebuchet MS, Helvetica, sans-serif",
                "Verdana, Geneva, sans-serif",
            ],
        },
    };
}

import ClassicEditor from "@ckeditor/ckeditor5-editor-classic/src/classiceditor";
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
import TableToolbar from "@ckeditor/ckeditor5-table/src/tabletoolbar";
import TableProperties from "@ckeditor/ckeditor5-table/src/tableproperties";
import TableCellProperties from "@ckeditor/ckeditor5-table/src/tablecellproperties";
import TableColumnResize from "@ckeditor/ckeditor5-table/src/tablecolumnresize";
import TableCaption from "@ckeditor/ckeditor5-table/src/tablecaption";
import Link from "@ckeditor/ckeditor5-link/src/link";
import List from "@ckeditor/ckeditor5-list/src/list";
import TodoList from "@ckeditor/ckeditor5-list/src/todolist";
import Alignment from "@ckeditor/ckeditor5-alignment/src/alignment";
import SourceEditing from "@ckeditor/ckeditor5-source-editing/src/sourceediting";
import CKEditorInspector from "@ckeditor/ckeditor5-inspector";

const storageKeys = ["editorData1", "editorData2"];
const editorTextAreas = document.querySelectorAll("textarea");
const editors = [];

/**
 * check the local storage for any previously saved content
 */
function preFillEditors() {
    let storageValue: string | null;
    storageKeys.forEach((storageKey, index) => {
        if (window.localStorage.getItem(storageKey)) {
            storageValue = window.localStorage.getItem(storageKey) as string;
            editorTextAreas[index].insertAdjacentHTML("beforeend", storageValue);
        }
    });
}

/**
 * create the ckeditor instance
 */
function createEditor() {
    ClassicEditor.create(editorTextArea, {
        plugins: [
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
            Link,
            List,
            TodoList,
            Alignment,
            SourceEditing,
        ],
        toolbar: [
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
                ],
            },
            "|",
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
                items: ["insertImage", "mediaEmbed", "insertTable", "specialCharacters", "emoji"],
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
            "sourceEditing",
        ],
        style: {
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
            ],
        },
        heading: {
            options: [
                { model: "paragraph", title: "Paragraph", class: "ck-heading_paragraph" },
                { model: "heading2", view: "h2", title: "Heading 2", class: "ck-heading_heading2" },
                { model: "heading3", view: "h3", title: "Heading 3", class: "ck-heading_heading3" },
                { model: "heading4", view: "h4", title: "Heading 4", class: "ck-heading_heading4" },
            ],
        },
        image: {
            toolbar: [
                "imageStyle:block",
                "imageStyle:side",
                "|",
                "toggleImageCaption",
                "imageTextAlternative",
                "|",
                "linkImage",
            ],
        },
        table: {
            contentToolbar: [
                "tableColumn",
                "tableRow",
                "mergeTableCells",
                "tableProperties",
                "tableCellProperties",
                "toggleTableCaption",
            ],
        },
    })
        .then((newEditor) => {
            editor = newEditor;
            CKEditorInspector.attach(editor);
        })
        .catch((error) => {
            console.error(error.stack);
        });
}

/**
 * on selection save the editor content into local storage
 */
function initEditorSaves() {
    const saveButton = document.getElementById("save-button") as HTMLButtonElement;
    saveButton.addEventListener("click", () => {
        storageKeys.forEach((storageKey, index) => {
            const editorData = editors[index].getData() as string;
            window.localStorage.setItem(storageKey, editorData);
        });
    });
}

preFillEditors();
createEditors();
initEditorSaves();

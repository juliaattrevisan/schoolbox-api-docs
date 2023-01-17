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
import RemoveFormat from "@ckeditor/ckeditor5-remove-format/src/removeformat";
import Link from "@ckeditor/ckeditor5-link/src/link";
import DocumentList from "@ckeditor/ckeditor5-list/src/documentlist";
import Alignment from "@ckeditor/ckeditor5-alignment/src/alignment";

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
            RemoveFormat,
            Link,
            DocumentList,
            Alignment,
        ],
        toolbar: [
            "style",
            "|",
            "heading",
            "|",
            "bold",
            "italic",
            {
                label: "More basic styles",
                icon: "threeVerticalDots",
                items: ["underline", "strikethrough", "subscript", "superscript"],
            },
            "|",
            "removeFormat",
            "|",
            "link",
            "|",
            {
                label: "Lists",
                icon: false,
                items: ["numberedList", "bulletedList"],
            },
            "|",
            "alignment",
        ],
    })
        .then((newEditor) => {
            editors.push(newEditor);
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

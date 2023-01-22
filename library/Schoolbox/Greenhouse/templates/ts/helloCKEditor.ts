import ClassicEditor from "@ckeditor/ckeditor5-editor-classic/src/classiceditor";
import Essentials from "@ckeditor/ckeditor5-essentials/src/essentials";
import Paragraph from "@ckeditor/ckeditor5-paragraph/src/paragraph";
import Bold from "@ckeditor/ckeditor5-basic-styles/src/bold";
import Italic from "@ckeditor/ckeditor5-basic-styles/src/italic";

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
function createEditors() {
    editorTextAreas.forEach((editorTextArea) => {
        ClassicEditor.create(editorTextArea, {
            plugins: [Essentials, Paragraph, Bold, Italic],
            toolbar: ["bold", "italic"],
        })
            .then((newEditor) => {
                editors.push(newEditor);
            })
            .catch((error) => {
                console.error(error.stack);
            });
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

import ClassicEditor from "@ckeditor/ckeditor5-editor-classic/src/classiceditor";
import Essentials from "@ckeditor/ckeditor5-essentials/src/essentials";
import Paragraph from "@ckeditor/ckeditor5-paragraph/src/paragraph";
import Bold from "@ckeditor/ckeditor5-basic-styles/src/bold";
import Italic from "@ckeditor/ckeditor5-basic-styles/src/italic";

let editor1;
let editor2;
const storageKey1 = "editorData1";
const storageKey2 = "editorData2";
const editorTextArea1 = document.querySelector("#editor-1") as HTMLTextAreaElement;
const editorTextArea2 = document.querySelector("#editor-2") as HTMLTextAreaElement;

/**
 * check the local storage for any previously saved content
 */
function preFillEditors() {
    let storageValue: string | null;
    if (window.localStorage.getItem(storageKey1)) {
        storageValue = window.localStorage.getItem(storageKey1) as string;
        editorTextArea1.insertAdjacentHTML("beforeend", storageValue);
    }
    if (window.localStorage.getItem(storageKey2)) {
        storageValue = window.localStorage.getItem(storageKey2) as string;
        editorTextArea2.insertAdjacentHTML("beforeend", storageValue);
    }
}

/**
 * create the ckeditor instance
 */
function createEditors() {
    ClassicEditor.create(editorTextArea1, {
        plugins: [Essentials, Paragraph, Bold],
        toolbar: ["bold"],
    })
        .then((newEditor) => {
            editor1 = newEditor;
        })
        .catch((error) => {
            console.error(error.stack);
        });
    ClassicEditor.create(editorTextArea2, {
        plugins: [Essentials, Paragraph, Italic],
        toolbar: ["italic"],
    })
        .then((newEditor) => {
            editor2 = newEditor;
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
        const editorData1 = editor1.getData() as string;
        window.localStorage.setItem(storageKey1, editorData1);
        const editorData2 = editor2.getData() as string;
        window.localStorage.setItem(storageKey2, editorData2);
    });
}

preFillEditors();
createEditors();
initEditorSaves();

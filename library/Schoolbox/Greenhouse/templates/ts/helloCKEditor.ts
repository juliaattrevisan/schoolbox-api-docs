import ClassicEditor from "@ckeditor/ckeditor5-editor-classic/src/classiceditor";
import Essentials from "@ckeditor/ckeditor5-essentials/src/essentials";
import Paragraph from "@ckeditor/ckeditor5-paragraph/src/paragraph";
import Bold from "@ckeditor/ckeditor5-basic-styles/src/bold";
import Italic from "@ckeditor/ckeditor5-basic-styles/src/italic";

let editor;
const storageKey = "editorData";
const editorTextArea = document.querySelector("#editor") as HTMLTextAreaElement;

/**
 * check the local storage for any previously saved content
 */
function preFillEditor() {
    let storageValue: string | null;
    if (window.localStorage.getItem(storageKey)) {
        storageValue = window.localStorage.getItem(storageKey) as string;
        editorTextArea.insertAdjacentHTML("beforeend", storageValue);
    }
}

/**
 * create the ckeditor instance
 */
function createEditor() {
    ClassicEditor.create(editorTextArea, {
        plugins: [Essentials, Paragraph, Bold, Italic],
        toolbar: ["bold", "italic"],
    })
        .then((newEditor) => {
            editor = newEditor;
        })
        .catch((error) => {
            console.error(error.stack);
        });
}

/**
 * on selection save the editor content into local storage
 */
function initEditorSave() {
    const saveButton = document.getElementById("save-button") as HTMLButtonElement;
    saveButton.addEventListener("click", () => {
        const editorData = editor.getData() as string;
        window.localStorage.setItem(storageKey, editorData);
    });
}

preFillEditor();
createEditor();
initEditorSave();

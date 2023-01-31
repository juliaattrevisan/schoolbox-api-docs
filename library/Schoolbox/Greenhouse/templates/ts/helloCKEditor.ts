import ClassicEditor from "@ckeditor/ckeditor5-editor-classic/src/classiceditor";
import { getDefault } from "@js/editor/configDefault";
import CKEditorInspector from "@ckeditor/ckeditor5-inspector";
import { insertElementAfter } from "@js/util/domUtils";

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
initEditorSaves();

/**
 * store the newly created editor instance to we can get its data on save
 */
document.addEventListener("EDITOR__CREATED", (event: CustomEvent) => {
    editor = event.detail.editor;
});

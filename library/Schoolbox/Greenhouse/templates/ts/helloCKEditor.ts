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
    // editor-1 is the first editor on the page, so we want to add it to the front of the array
    if (editors.length === 1 && event.detail.editor.sourceElement.getAttribute("id") === "editor-1") {
        editors.unshift(event.detail.editor);
    } else {
        editors.push(event.detail.editor);
    }
});

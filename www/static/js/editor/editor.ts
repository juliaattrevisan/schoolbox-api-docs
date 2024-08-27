/**
 * To create CKEditor instances.
 * Refer to templates/core/pantry/editor.htm for further details on how to use this script.
 */
import { ClassicEditor } from "@ckeditor/ckeditor5-editor-classic";
import { InlineEditor } from "@ckeditor/ckeditor5-editor-inline";
import { getConfig } from "@js/editor/config/editorConfig";
import { EditorType, EditorToolbar, allowedEditorElements } from "@js/editor/types/editorTypes";
import type {
    SbxEditor,
    SbxEditorConfig,
    SbxEditorElement,
    PartialSbxEditorConfig,
} from "@js/editor/types/editorTypes";
import { EditorEvent } from "@js/editor/event/editorEvent";
import type {
    EditorCreatedEventInfo,
    EditorDeletedEventInfo,
    EditorDeleteEventInfo,
} from "@js/editor/event/editorEvent";
import { initCheckData } from "@js/editor/config/editorCheckData";
import { modifyEditor } from "@js/editor/config/editorCreated";
import "@js/editor/styles/editor.css"; // to overwrite some of the styles

/**
 * Add in any additional setup required and then dispatch that the editor is ready
 * @param { SbxEditorElement } sourceElement - the sourceElement the CKEditor will replace
 * @param { SbxEditor } editor - the CKEditor instance
 * @param { SbxEditorConfig } config - the CKEditor config object
 */
function onCreated(sourceElement: SbxEditorElement, editor: SbxEditor, config: SbxEditorConfig) {
    modifyEditor(sourceElement, editor, config);
    sourceElement.dispatchEvent(
        new CustomEvent<EditorCreatedEventInfo>(EditorEvent.EDITOR__CREATED, {
            detail: {
                editor,
                sourceElement,
            },
        }),
    );
}

/**
 * set up any default config when it's not been defined
 * @param { PartialSbxEditorConfig } config - the config to use when creating the editor
 * @returns { SbxEditorConfig } - the config with defaults added
 */
function addDefaultConfig(config: PartialSbxEditorConfig): SbxEditorConfig {
    const filledConfig: SbxEditorConfig = {
        ...config,
        toolbar: config.toolbar || EditorToolbar.DEFAULT,
        type: config.type || EditorType.CLASSIC,
        maxCharCount: config.maxCharCount || window.sbProps.MAX_BYTES_MYSQL_TEXT,
    };

    return filledConfig;
}

/**
 * create the ckeditor instance
 * @param { SbxEditorElement } sourceElement - the sourceElement the CKEditor will replace
 * @param { SbxEditorConfig } config - the config to use when creating the editor
 */
async function create(sourceElement: SbxEditorElement, config: SbxEditorConfig) {
    const editorClassMap = {
        [EditorType.CLASSIC]: ClassicEditor,
        [EditorType.INLINE]: InlineEditor,
    };

    const editor = await editorClassMap[config.type].create(sourceElement, getConfig(config));
    onCreated(sourceElement, editor, config);
}

/**
 * Listen for init events to create any instances
 * @param { SbxEditorConfig } config - the config to use when creating the editor
 */
async function onCreate(config: SbxEditorConfig) {
    let sourceElements: HTMLElement[] = [];
    config.targetElements.forEach((targetElement) => {
        // assume that each targetElement could contain multiple instances
        if (typeof targetElement === "string") {
            sourceElements = sourceElements.concat(Array.from(document.querySelectorAll(targetElement)));
        } else {
            sourceElements.push(targetElement);
        }
    });

    await Promise.all(
        sourceElements.map(async (sourceElement) => {
            // Sometimes the targetElement selector may match elements that are not intended for a CKEditor,
            // for example, if "[data-cke]" is used, it will match the CKEditor <style> element,
            // this results in a sanitized style element, breaking various editor styles.
            //
            // To solve this, we check that the element is within the set of allowed CKEditor elements
            if (!allowedEditorElements.includes(sourceElement.tagName.toUpperCase())) {
                console.warn(
                    `CKEditor: Element "${sourceElement.tagName}" is not allowed to be replaced by CKEditor:`,
                    sourceElement,
                );
                return;
            }

            // At this point, sourceElement is a SbxEditorElement (since the check above passed)
            const editorSourceElement = sourceElement as SbxEditorElement;
            initCheckData(editorSourceElement, config);
            await create(editorSourceElement, config);
        }),
    );
}

/**
 * Respond to a request to delete an editor instance, by deleting the editor instance, and then firing another event
 * informing that an editor instance has been deleted.
 * @param { EditorDeleteEventInfo } eventDetail - Information about the event.
 */
function onDelete(eventDetail: EditorDeleteEventInfo) {
    const sourceElement = eventDetail.editor.sourceElement as HTMLTextAreaElement;

    let keyOfEditorToDelete = null;
    if (sourceElement.id !== undefined) {
        keyOfEditorToDelete = sourceElement.id;
    } else if (sourceElement.name !== undefined) {
        keyOfEditorToDelete = sourceElement.name;
    }

    if (keyOfEditorToDelete !== null) {
        const existingEditor = window.sbProps.editors[keyOfEditorToDelete];
        if (existingEditor === undefined) {
            throw new Error(`Editor with key ${keyOfEditorToDelete} does not exist in window.sbProps.editors.`);
        }

        void existingEditor.destroy();
        delete window.sbProps.editors[keyOfEditorToDelete];

        let submitButton = document.getElementById("save-button");
        if (!(submitButton instanceof HTMLButtonElement || submitButton instanceof HTMLInputElement)) {
            submitButton = null;
        }

        document.dispatchEvent(
            new CustomEvent<EditorDeletedEventInfo>(EditorEvent.EDITOR__DELETED, {
                detail: {
                    keyOfDeletedEditor: keyOfEditorToDelete,
                    containingFormId: "lesson-plan-template-form",
                    submitButtons: submitButton ? [submitButton] : [],
                },
            }),
        );
    }
}

document.addEventListener(EditorEvent.EDITOR__CREATE, (event) => {
    const configWithDefaults = addDefaultConfig((event as CustomEvent<PartialSbxEditorConfig>).detail);
    void onCreate(configWithDefaults);
});
document.addEventListener(EditorEvent.EDITOR__DELETE, (event) => {
    onDelete((event as CustomEvent<EditorDeleteEventInfo>).detail);
});

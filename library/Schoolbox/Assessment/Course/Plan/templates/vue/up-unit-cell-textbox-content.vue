<script setup lang="ts">
/**
 * The main component for showing the content of a textbox on the unit planner
 */
import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import { EditorType } from "@js/editor/types/editorTypes";
import type { EditorCreatedEventInfo } from "@js/editor/event/editorEvent";
import type { InlineEditor } from "@ckeditor/ckeditor5-editor-inline";
import MediaArticle from "@schoolboxVue/components/media-article.vue";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { useUnitPlannerTextboxStore } from "./store/unitPlannerTextboxStore";
import { Column } from "./unitPlannerConfig";
import { Unit } from "./unitPlannerTypes";

interface Props {
    unit: Unit;
    column: Column;
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();

const initialTextboxContent: string = props.unit.textboxes.items[props.column.label]?.body ?? "";

const editorContent = window.Vue.ref(initialTextboxContent);
const editorContentEl = window.Vue.ref<HTMLElement | null>(null);
const editorInitialised = window.Vue.ref(false);
const editorCreated = window.Vue.ref(false);
const editorId = `unit-planner-textbox-${props.unit.id}-${props.column.id}`;
const textboxStore = useUnitPlannerTextboxStore();
let editor: InlineEditor;

/**
 * Is this cell empty?
 * Used by the parent component to determine whether to show the empty state
 */
const isEmpty = window.Vue.computed(() => {
    // If there is unsaved content, the cell is not empty
    if (textboxStore.unsavedTextboxes[props.unit.id]?.[props.column.id] !== undefined) {
        return false;
    }

    // Otherwise, the cell is empty if there is no content in the body yet
    return editorContent.value === "";
});

/**
 * When a new editor is created
 * @param event - the editor created event data
 */
function onEditorCreated(event: EditorCreatedEventInfo): void {
    editor = event.editor as InlineEditor;

    editor.ui.focusTracker.on("change:isFocused", (_, __, isFocused: boolean) => {
        store.updateFocusedUnitCell(props.unit.id, props.column, isFocused);
    });

    editor.model.document.on("change", () => {
        store.updateFocusedUnitCell(props.unit.id, props.column, true);
        window.Vue.nextTick()
            .then(() => {
                editorContent.value = editor.getData();
                textboxStore.updateTextboxContent(props.unit.id, props.column, editorContent.value);
            })
            .catch(() => {
                // noop
            });
    });

    const storeTextbox = store.units.find((unit) => unit.id === props.unit.id)?.textboxes.items[props.column.label];
    if (storeTextbox) {
        storeTextbox.body = editor.getData();
    }

    editorCreated.value = true;

    // Fix the balloon toolbar position
    setTimeout(() => editor.ui.update());

    // If the editor was focused initially, we need to update the store
    window.Vue.nextTick(() => {
        if (editor.ui.focusTracker.isFocused) {
            store.updateFocusedUnitCell(props.unit.id, props.column, true);
        }
    }).catch(() => {
        // noop
    });
}

/**
 * Initialize the CKEditor instance
 */
async function initEditor(): Promise<void> {
    if (!editorContentEl.value) return;
    if (editorInitialised.value) return;

    // Avoid initialising the editor multiple times
    editorInitialised.value = true;

    await new Promise((resolve) => {
        useEditor(
            editorContent,
            `#${editorId}`,
            {
                targetElements: [`#${editorId}`],
                type: EditorType.INLINE,
            },
            true,
            (e) => {
                onEditorCreated(e);
                resolve(true);
            },
        );
    });
}

/**
 * Focus the editor
 */
async function focusEditor(): Promise<void> {
    // Don't focus if the textbox hasn't been created yet
    if (!textboxStore.textboxIsCreated(props.unit.id, props.column)) return;

    // Don't focus if the user doesn't have permission to edit the textbox
    if (props.unit.textboxes._routes.createTextbox === null) return;

    await initEditor(); // Ensure the editor is initialised before focusing

    editor.focus();

    // to assign the cursor to the end of the editor
    editor.model.change((writer) => {
        const root = editor.model.document.getRoot();
        if (root) {
            writer.setSelection(writer.createPositionAt(root, "end"));
        }
    });
}

// Allow the parent component to focus the editor and identify if the cell is empty
defineExpose({ focusEditor, isEmpty });
</script>

<template>
    <div
        class="editor-wrapper"
        :style="{ display: textboxStore.textboxIsCreated(props.unit.id, props.column) ? 'block' : 'none' }"
    >
        <media-article
            :id="editorId"
            ref="editorContentEl"
            tag="div"
            class="editor-content"
            :content="initialTextboxContent"
        ></media-article>
        <div class="clearfix"></div>
    </div>
</template>

<style>
.editor-wrapper {
    z-index: var(--sbx-depth-default);
}
.editor-content {
    border: 0 !important;
    padding: 8px !important;
    z-index: auto !important; /* so any content inside the editor sits within the cell and doesn't sit above the View more button */
}

/* Specificity to override the CKEditor border, we handle it in the parent vue component instead */
.editor-content.ck.ck-content.ck-editor__editable.ck-focused,
.ck.ck-editor__main .ck-source-editing-area textarea:focus {
    border: 0 !important;
}

.editor-wrapper {
    padding-bottom: 2px;
}
</style>

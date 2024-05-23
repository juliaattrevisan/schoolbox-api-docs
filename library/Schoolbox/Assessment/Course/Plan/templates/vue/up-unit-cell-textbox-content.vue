<script setup lang="ts">
/**
 * The main component for showing the content of a textbox on the unit planner
 */
import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import { EditorType } from "@js/editor/types/editorTypes";
import type { EditorCreatedEventInfo } from "@js/editor/event/editorEvent";
import type { InlineEditor } from "@ckeditor/ckeditor5-editor-inline";
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

// Whether the textbox exists in the backend, or if it still needs to be created
const textboxIsCreated = window.Vue.computed(() => {
    return (store.units.find((unit) => unit.id === props.unit.id)?.textboxes[props.column.label].id || 0) >= 0;
});

const editorContent = window.Vue.ref(props.unit.textboxes[props.column.label].body);
const editorContentEl = window.Vue.ref<HTMLElement | null>(null);
const editorInitialised = window.Vue.ref(false);
const editorCreated = window.Vue.ref(false);
const textboxStore = useUnitPlannerTextboxStore();
let editor: InlineEditor;

/**
 * Focus the editor
 */
function focusEditor() {
    if (!editorContentEl.value) return;
    if (!textboxIsCreated.value) return; // Don't focus if the textbox hasn't been created yet

    editor?.focus();
    // to assign the cursor to the end of the editor
    editor?.model.change((writer) => {
        const root = editor.model.document.getRoot();
        if (root) {
            writer.setSelection(writer.createPositionAt(root, "end"));
        }
    });
}

// Allow the parent component to focus the editor
defineExpose({ focusEditor });

/**
 * When a new editor is created
 * @param { EditorCreatedEventInfo } event - the editor created event data
 */
function onEditorCreated(event: EditorCreatedEventInfo) {
    editor = event.editor as InlineEditor;

    editor.ui.focusTracker.on("change:isFocused", (_, __, isFocused: boolean) => {
        store.updateFocusedUnitCell(props.unit.id, props.column, isFocused);
    });

    editor.model.document.on("change", () => {
        store.updateFocusedUnitCell(props.unit.id, props.column, true);
        window.Vue.nextTick()
            .then(() => {
                textboxStore.updateTextboxContent(props.unit.id, props.column, editor.getData());
            })
            .catch(() => {
                // noop
            });
    });

    const storeTextbox = store.units.find((unit) => unit.id === props.unit.id)?.textboxes[props.column.label];
    if (storeTextbox) {
        storeTextbox.body = editor.getData();
    }

    editorCreated.value = true;

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
function initEditor() {
    if (!editorContentEl.value) return;
    if (editorInitialised.value) return;

    editorInitialised.value = true;

    useEditor(
        editorContent,
        editorContentEl.value,
        {
            targetElements: [editorContentEl.value],
            type: EditorType.INLINE,
        },
        true,
        onEditorCreated,
    );
}

window.Vue.onMounted(initEditor);
</script>

<template>
    <div class="editor-wrapper" :style="{ display: textboxIsCreated ? 'block' : 'none' }">
        <!-- eslint-disable vue/no-v-html | initial textboxes are scrubbed-->
        <div ref="editorContentEl" class="editor-content" v-html="props.unit.textboxes[props.column.label].body"></div>
        <!-- eslint-enable vue/no-v-html -->
    </div>
</template>

<style scoped lang="scss">
.editor-wrapper {
    z-index: 1;
}
.editor-content {
    border: 0 !important;
    padding: 8px !important;
    z-index: auto !important; /* so any content inside the editor sits within the cell and doesn't sit above the View more button */
}

// Specificity to override the CKEditor border, we handle it in the parent vue component instead
.editor-content.ck.ck-content.ck-editor__editable.ck-focused,
.ck.ck-editor__main .ck-source-editing-area textarea:focus {
    border: 0 !important;
}

.editor-wrapper {
    padding-bottom: 2px;
}
</style>

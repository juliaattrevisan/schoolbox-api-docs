<script setup lang="ts">
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
const emit = defineEmits(["updateContentHeight"]);
const store = useUnitPlannerStore();
const textboxStore = useUnitPlannerTextboxStore();

// Whether the textbox exists in the backend, or if it still needs to be created
const textboxIsCreated = window.Vue.computed(() => {
    return (store.units.find((unit) => unit.id === props.unit.id)?.textboxes[props.column.label].id || 0) >= 0;
});

const editorContent = window.Vue.ref(props.unit.textboxes[props.column.label].body);
const editorContentEl = window.Vue.ref<HTMLElement | null>(null);
const editorInitialised = window.Vue.ref(false);
const editorCreated = window.Vue.ref(false);

/**
 * Focus the editor
 */
function focusEditor() {
    if (!editorContentEl.value) return;
    if (!textboxIsCreated.value) return; // Don't focus if the textbox hasn't been created yet

    editorContentEl.value.focus();
}

// Allow the parent component to focus the editor
defineExpose({ focusEditor });

/**
 * When a new editor is created
 * @param { EditorCreatedEventInfo } event - the editor created event data
 */
function onEditorCreated(event: EditorCreatedEventInfo) {
    const editor = event.editor as InlineEditor;

    editor.editing.view.document.on("change:isFocused", (_, __, isFocused: boolean) => {
        store.updateFocusedUnitCell(props.unit.id, props.column, isFocused);
    });

    editor.model.document.on("change", () => {
        store.updateFocusedUnitCell(props.unit.id, props.column, true);
        window.Vue.nextTick()
            .then(() => {
                emit("updateContentHeight");
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

    emit("updateContentHeight");
    editorCreated.value = true;

    // If the editor was focused initially, we need to update the store
    window.Vue.nextTick(() => {
        if (editor.editing.view.document.isFocused) {
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
.editor-content {
    z-index: auto !important;
    padding: 8px !important;
    border: 0 !important;
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

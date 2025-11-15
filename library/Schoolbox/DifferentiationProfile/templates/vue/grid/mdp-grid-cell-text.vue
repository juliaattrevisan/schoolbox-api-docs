<script setup lang="ts">
/**
 * Display text cell for a student in the MDP grid
 */

import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import { EditorType } from "@js/editor/types/editorTypes";
import type { EditorCreatedEventInfo } from "@js/editor/event/editorEvent";
import type { InlineEditor } from "ckeditor5";
import MediaArticle from "@schoolboxVue/components/media-article.vue";
import EmptyState from "@schoolboxVue/components/empty-state.vue";
import type { AdjustmentText, Profile, ProfileTextKeys } from "../../js/types/manageDifferentiationProfilesTypes";
import { useMdpSaveStore } from "../store/mdpSaveStore";
import MdpGridCellTextAction from "./mdp-grid-cell-text-action.vue";

const props = defineProps<{
    profile: Profile;
    text: AdjustmentText;
    headerKey: ProfileTextKeys;
}>();

const mdpSaveStore = useMdpSaveStore();

const currentText = window.Vue.ref<AdjustmentText>(props.text);
const initialTextboxContent: string = currentText.value ?? "";
const editorContent = window.Vue.ref(initialTextboxContent);
const editorContentEl = window.Vue.ref<HTMLElement | null>(null);
const editorInitialised = window.Vue.ref(false);
const editorCreated = window.Vue.ref(false);
const editorId = `mdp-textbox-${props.profile.student.id}-${props.headerKey}`;
const hasChanged = window.Vue.computed(() => {
    return currentText.value !== props.text;
});
const isSaving = window.Vue.ref(false);
const isFocused = window.Vue.ref(false);
// const textboxStore = useUnitPlannerTextboxStore();
let editor: InlineEditor;

/**
 * When a new editor is created
 * @param event - the editor created event data
 */
function onEditorCreated(event: EditorCreatedEventInfo): void {
    editor = event.editor as InlineEditor;
    // show the empty state again if no text is entered
    editor.ui.focusTracker.on("change:isFocused", (_, __, $isFocused: boolean) => {
        isFocused.value = $isFocused;
        if (!isFocused.value && currentText.value === "") {
            currentText.value = null;
        }
    });
    // keep the currentText in sync with the editor content
    editor.model.document.on("change", () => {
        window.Vue.nextTick()
            .then(() => {
                currentText.value = editor.getData();
            })
            .catch(() => {
                // noop
            });
    });
    editorCreated.value = true;
    // Fix the balloon toolbar position
    setTimeout(() => editor.ui.update());
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
    // Ensure the editor is initialised before focusing
    await initEditor();
    // to trigger the display of the editor if it was not displayed yet
    if (currentText.value === null) {
        currentText.value = "";
    }
    await window.Vue.nextTick();
    editor.focus();
    isFocused.value = true;
    // to assign the cursor to the end of the editor
    editor.model.change((writer) => {
        const root = editor.model.document.getRoot();
        if (root) {
            writer.setSelection(writer.createPositionAt(root, "end"));
        }
    });
}

/**
 * When the add text button is clicked.
 */
function onAdd(): void {
    void focusEditor();
}

/**
 * When the modify button is clicked.
 */
function onModify(): void {
    if (currentText.value === null) {
        return;
    }
    void focusEditor();
}

/**
 * When the save button is clicked.
 */
function onSave(): void {
    if (!editorCreated.value) return;
    const { profile } = props;
    profile[props.headerKey] = currentText.value;
    void mdpSaveStore.save(profile);
}
</script>

<template>
    <div :style="{ cursor: currentText !== null ? 'text' : 'default' }" @click="onModify">
        <empty-state v-if="currentText === null" icon="icon-textbox">
            <button @click.prevent="onAdd">Add content to this text area</button>
        </empty-state>
        <div class="editor-wrapper" :style="{ display: currentText !== null ? 'block' : 'none' }">
            <media-article
                :id="editorId"
                ref="editorContentEl"
                tag="div"
                class="editor-content"
                :content="initialTextboxContent"
            ></media-article>
            <div class="clearfix"></div>
        </div>
    </div>
    <mdp-grid-cell-text-action
        :profile="props.profile"
        :header-key="props.headerKey"
        :has-changed="hasChanged"
        :is-saving="isSaving"
        :is-focused="isFocused"
        @modify="onModify"
        @save="onSave"
    />
</template>

<style>
.editor-wrapper {
    padding-bottom: 2px;
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
</style>

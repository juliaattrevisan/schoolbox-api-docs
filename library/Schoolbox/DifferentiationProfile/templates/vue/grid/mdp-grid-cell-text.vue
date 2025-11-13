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
import type { AdjustmentText, Profile } from "../../js/types/manageDifferentiationProfilesTypes";
import MdpGridCellTextAction from "./mdp-grid-cell-text-action.vue";

const props = defineProps<{
    profile: Profile;
    text: AdjustmentText | null;
    headerKey: string;
}>();

const currentText = window.Vue.ref<AdjustmentText | null>(props.text);

const initialTextboxContent: string = currentText.value ?? "";

const editorContent = window.Vue.ref(initialTextboxContent);
const editorContentEl = window.Vue.ref<HTMLElement | null>(null);
const editorInitialised = window.Vue.ref(false);
const editorCreated = window.Vue.ref(false);
const editorId = `mdp-textbox-${props.profile.student.id}-${props.headerKey}`;
// const textboxStore = useUnitPlannerTextboxStore();
let editor: InlineEditor;

/**
 * When a new editor is created
 * @param event - the editor created event data
 */
function onEditorCreated(event: EditorCreatedEventInfo): void {
    editor = event.editor as InlineEditor;
    // show the empty state again if no text is entered
    editor.ui.focusTracker.on("change:isFocused", (_, __, isFocused: boolean) => {
        if (!isFocused && currentText.value === "") {
            currentText.value = null;
        }
    });
    // keep the currentText in sync with the editor content
    editor.model.document.on("change", () => {
        // store.updateFocusedUnitCell(props.unit.id, props.column, true);
        window.Vue.nextTick()
            .then(() => {
                currentText.value = editor.getData();
                // textboxStore.updateTextboxContent(props.unit.id, props.column, editorContent.value);
            })
            .catch(() => {
                // noop
            });
    });

    // const storeTextbox = store.units.find((unit) => unit.id === props.unit.id)?.textboxes.items[props.column.label];
    // if (storeTextbox) {
    //     storeTextbox.body = editor.getData();
    // }

    editorCreated.value = true;

    // Fix the balloon toolbar position
    setTimeout(() => editor.ui.update());

    // If the editor was focused initially, we need to update the store
    // window.Vue.nextTick(() => {
    //     if (editor.ui.focusTracker.isFocused) {
    //         store.updateFocusedUnitCell(props.unit.id, props.column, true);
    //     }
    // }).catch(() => {
    //     // noop
    // });
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
function onAddText(): void {
    void focusEditor();
}

/**
 * When the modify button is clicked.
 */
function onModifySelected(): void {
    void focusEditor();
}

/**
 * When the save button is clicked.
 */
function onSaveSelected(): void {
    // if (!editorCreated.value) return;
    // editorContent.value = editor.getData();
    // text.value = editorContent.value;
}
</script>

<template>
    <div>
        <empty-state v-if="currentText === null" icon="icon-textbox">
            <button @click.prevent="onAddText">Add content to this text area</button>
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
        @modify="onModifySelected"
        @save="onSaveSelected"
    />
</template>

<style scoped></style>

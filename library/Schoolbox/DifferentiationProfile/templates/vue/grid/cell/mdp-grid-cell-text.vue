<script setup lang="ts">
/**
 * Display text cell for a student in the MDP grid, allowing editing via CKEditor.
 */

import { useEditor } from "@schoolboxVue/composables/editor/useEditor";
import {
    useLazyGridRowsHeightStore,
    ANIMATION_DURATION,
} from "@schoolboxVue/components/grid/lazyGrid/store/lgRowsHeightStore";
import { useContentHeight } from "@schoolboxVue/components/grid/lazyGrid/composable/useContentHeight";
import LgCellShowMore from "@schoolboxVue/components/grid/lazyGrid/lg-cell-show-more.vue";
import { EditorType, EditorToolbar } from "@js/editor/types/editorTypes";
import type { EditorCreatedEventInfo } from "@js/editor/event/editorEvent";
import type { InlineEditor } from "ckeditor5";
import MediaArticle from "@schoolboxVue/components/media-article.vue";
import EmptyState from "@schoolboxVue/components/empty-state.vue";
import LgCellActionSave from "@schoolboxVue/components/grid/lazyGrid/lg-cell-action-save.vue";
import type { Profile, ProfileTextKeys } from "../../../js/types/manageDifferentiationProfilesTypes";
import { useHeaderStore } from "../../store/mdpHeaderStore";
import { useSaveStore } from "../../store/mdpSaveStore";

const props = defineProps<{
    profile: Profile;
    headerKey: ProfileTextKeys;
    text: string | null;
}>();

const headerStore = useHeaderStore();
const lgRowsHeightStore = useLazyGridRowsHeightStore();
const saveStore = useSaveStore();
const contentEl = window.Vue.ref<HTMLElement | null>(null);
const componentActionEl = window.Vue.ref<HTMLElement | null>(null);
const animationDuration = `${ANIMATION_DURATION}s`; // duration for animations in seconds

/**
 * To update the height of the cell based on content and action heights
 * and provide these heights for the 'View more' functionality
 */
const { contentHeight, componentActionHeight } = useContentHeight(
    props.profile.student.id,
    props.headerKey,
    contentEl,
    componentActionEl,
    lgRowsHeightStore,
);

// #region modifying the text content with CKEditor

const initialTextboxContent = window.Vue.ref(props.text ?? "");
const currentText = window.Vue.ref<string | null>(props.text);
const editorContent = window.Vue.ref(props.text ?? "");
const editorContentEl = window.Vue.ref<HTMLElement | null>(null);
const editorId = `mdp-textbox-${props.profile.student.id}-${props.headerKey}`;
let editor: InlineEditor;
const isEditorInitialised = window.Vue.ref(false);
const isEditorCreated = window.Vue.ref(false);
const isSaving = window.Vue.ref(false);

/**
 * Whether the content of the cell is focused
 */
const isContentFocused = window.Vue.computed(() => {
    return (lgRowsHeightStore.focusedCells?.[props.profile.student.id] ?? []).includes(props.headerKey);
});

/**
 * Whether the content of the cell is empty (and not focused)
 */
const isContentEmpty = window.Vue.computed(() => {
    // If this cell is focused, treat it as not empty
    if (isContentFocused.value) return false;
    // Otherwise, check if we have actual content
    return currentText.value === null;
});

const hasContentChanged = window.Vue.computed(() => currentText.value !== props.text); // to display 'unsaved changes' state

/**
 * When a new editor is created
 * @param event - the editor created event data
 */
function onEditorCreated(event: EditorCreatedEventInfo): void {
    editor = event.editor as InlineEditor;
    editor.ui.focusTracker.on("change:isFocused", (_, __, $isFocused: boolean) => {
        // Update focus state in the store to enable auto-growing height
        lgRowsHeightStore.updateFocusedCell(props.profile.student.id, props.headerKey, $isFocused);
        // Show the empty state again if no text is entered
        if (!isContentFocused.value && currentText.value === "") {
            currentText.value = null;
        }
    });
    editor.model.document.on("change", () => {
        // keep the currentText in sync with the editor content
        window.Vue.nextTick()
            .then(() => {
                currentText.value = editor.getData();
            })
            .catch(() => {
                // noop
            });
    });
    isEditorCreated.value = true;
    // Fix the balloon toolbar position
    setTimeout(() => editor.ui.update());
    // If the editor was focused initially, we need to update the store
    window.Vue.nextTick(() => {
        lgRowsHeightStore.updateFocusedCell(props.profile.student.id, props.headerKey, true);
    }).catch(() => {
        // noop
    });
}

/**
 * Initialize the CKEditor instance
 */
async function initEditor(): Promise<void> {
    if (!editorContentEl.value) return;
    if (isEditorInitialised.value) return;
    // Avoid initialising the editor multiple times
    isEditorInitialised.value = true;
    await new Promise((resolve) => {
        useEditor(
            editorContent,
            `#${editorId}`,
            {
                targetElements: [`#${editorId}`],
                type: EditorType.INLINE,
                toolbar: EditorToolbar.COMMENT_AI,
                addToToolbar: [{ name: "insertTable", after: "link" }],
                removeFromToolbar: ["link", "mediaEmbed", "resource", "emoji"],
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
 * When the content is clicked check if we need to focus the editor
 * @param event - the click event
 */
function onContentClicked(event: MouseEvent): void {
    if (contentEl.value === null || !event.target) return;
    // If the target is not the contentEl, a child of the contentEl, or its parent (the cell),
    // then the click wasn't on the content, and instead on the view more button or component action.
    if (
        !contentEl.value.contains(event.target as Node) &&
        event.target !== contentEl.value &&
        event.target !== contentEl.value.parentElement
    ) {
        return;
    }
    // if the cell is already focused, there's no need to re-focus it
    if (isContentFocused.value) {
        return;
    }
    // Focus the editor
    void focusEditor();
}

/**
 * When the add/modify button is clicked.
 */
function onModify(): void {
    void focusEditor();
}

/**
 * Watch for external changes to the text prop and update the editor content accordingly
 */
window.Vue.watch(
    () => props.text,
    (newText) => {
        if (newText === currentText.value) {
            return;
        }
        currentText.value = newText;
        editorContent.value = newText ?? "";
        if (isEditorCreated.value) {
            // update the editor data if it's already created
            editor.setData(editorContent.value);
        } else {
            // otherwise just update the initial content
            initialTextboxContent.value = editorContent.value;
        }
    },
);

// #endregion modifying the text content with CKEditor

/**
 * When the save button is clicked.
 */
function onSave(): void {
    if (!isEditorCreated.value) return;
    const toSave = {
        [props.headerKey]: currentText.value === "" ? null : currentText.value,
    };
    void saveStore.save(props.profile, toSave);
}
</script>

<template>
    <div
        class="grid-cell"
        :class="{
            'grid-cell--content-focused': isContentFocused,
        }"
        :style="{
            height: `${lgRowsHeightStore.rowDisplayHeights?.[props.profile.student.id]}px`,
            cursor: isContentEmpty ? 'pointer' : 'text',
        }"
        @click.prevent="onContentClicked"
    >
        <div ref="contentEl">
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
        <div class="content-container-fixed">
            <div v-if="!isContentEmpty" class="content-overlay">
                <lg-cell-show-more
                    :row-id="props.profile.student.id"
                    :header-key="props.headerKey"
                    :content-height="contentHeight"
                    :component-action-height="componentActionHeight"
                    :is-content-focused="isContentFocused"
                />
            </div>
            <div v-else class="content-overlay empty-state no-print">
                <empty-state icon="icon-textbox">
                    <button class="sbx-link-button" @click.prevent="onModify">
                        Add {{ headerStore.getTitleByKey(props.headerKey) }}
                    </button>
                </empty-state>
            </div>
            <div ref="componentActionEl">
                <lg-cell-action-save
                    :id="props.profile.student.id"
                    :header-key="props.headerKey"
                    :has-changed="hasContentChanged"
                    :is-saving="isSaving"
                    :is-focused="isContentFocused"
                    @modify="onModify"
                    @save="onSave"
                />
            </div>
        </div>
    </div>
</template>

<style lang="scss">
@import "@css/core/base/_variables.scss";

.grid-cell {
    overflow: clip; // Use clip to avoid focus scrolling the cell content
    position: relative;
    text-overflow: ellipsis;
    transition: height v-bind(animationDuration) ease;
    &--content-focused {
        .content-container-fixed .content-overlay {
            border: 1px solid $form-border-color !important;
        }
    }
}

.content-container-fixed {
    bottom: 0;
    cursor: default;
    display: flex;
    flex-direction: column;
    height: 100%;
    justify-content: end;
    left: 0;
    pointer-events: none;
    position: absolute;
    width: 100%;
    z-index: calc(var(--sbx-depth-default) + 1); // Always appear above the content
    .content-overlay {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        justify-content: end;
        margin-bottom: 0;
        width: 100%;
        &.empty-state {
            justify-content: center;
            pointer-events: all;
        }
    }
}

.editor-wrapper {
    padding-bottom: 2px;
    width: 100%;
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

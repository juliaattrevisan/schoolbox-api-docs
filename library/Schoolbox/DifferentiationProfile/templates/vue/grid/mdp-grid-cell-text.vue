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
import type { Profile, ProfileTextKeys } from "../../js/types/manageDifferentiationProfilesTypes";
import { useMdpHeaderStore } from "../store/mdpHeaderStore";
import { useMdpRowsStore } from "../store/mdpRowsStore";
import { useMdpSaveStore } from "../store/mdpSaveStore";
import MdpGridCellAction from "./mdp-grid-cell-action.vue";

const props = defineProps<{
    profile: Profile;
    headerKey: ProfileTextKeys;
    text: string | null;
}>();

const headerStore = useMdpHeaderStore();
const rowsStore = useMdpRowsStore();
const saveStore = useMdpSaveStore();
const currentText = window.Vue.ref<string | null>(props.text);
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
let editor: InlineEditor;

// #region Editor setup

/**
 * Whether the content of the cell is focused
 */
const isContentFocused = window.Vue.computed(() => {
    const store = rowsStore as unknown as { focusedCells?: Record<number, string[]> };
    return (store.focusedCells?.[props.profile.student.id] ?? []).includes(props.headerKey);
});

/**
 * When a new editor is created
 * @param event - the editor created event data
 */
function onEditorCreated(event: EditorCreatedEventInfo): void {
    editor = event.editor as InlineEditor;
    // show the empty state again if no text is entered
    editor.ui.focusTracker.on("change:isFocused", (_, __, $isFocused: boolean) => {
        // Update focus state in the store to enable auto-growing height
        if (typeof rowsStore.updateFocusedCell === "function") {
            rowsStore.updateFocusedCell(props.profile.student.id, props.headerKey, $isFocused);
        }
        if (!isContentFocused.value && currentText.value === "") {
            currentText.value = null;
        }
    });
    // keep the currentText in sync with the editor content
    editor.model.document.on("change", () => {
        // Ensure cell is marked as focused when content changes
        if (typeof rowsStore.updateFocusedCell === "function") {
            rowsStore.updateFocusedCell(props.profile.student.id, props.headerKey, true);
        }
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

    // If the editor was focused initially, we need to update the store
    window.Vue.nextTick(() => {
        if (editor.ui.focusTracker.isFocused && typeof rowsStore.updateFocusedCell === "function") {
            rowsStore.updateFocusedCell(props.profile.student.id, props.headerKey, true);
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
function onAdd(): void {
    void focusEditor();
}

/**
 * When the modify button is clicked.
 */
function onModify(): void {
    void focusEditor();
}

/**
 * When the save button is clicked.
 */
function onSave(): void {
    if (!editorCreated.value) return;
    const { profile } = props;
    profile[props.headerKey] = currentText.value;
    void saveStore.save(profile);
}

// #endregion Editor setup

// #region cell height and view more handling

const contentEl = window.Vue.ref<HTMLElement | null>(null);
const contentHeight = window.Vue.ref(0);
const componentActionEl = window.Vue.ref<HTMLElement | null>(null);
const componentActionHeight = window.Vue.ref(0);

/**
 * Whether the content of the cell is empty (and not focused)
 */
const isContentEmpty = window.Vue.computed(() => {
    // If this cell is focused, treat it as not empty
    if (isContentFocused.value) return false;
    // Otherwise, check if we have actual content
    return currentText.value === null;
});

/**
 * When the content is clicked
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
 * Whether to show the view more gradient and button
 */
const showViewMoreGradient = window.Vue.computed(() => {
    const displayHeights = rowsStore.rowDisplayHeights;
    if (!displayHeights) return false;
    const displayHeight = displayHeights[props.profile.student.id];
    if (!displayHeight) return false;
    return contentHeight.value + componentActionHeight.value > displayHeight;
});

/**
 * Whether to show the view more button
 * Only show when content exceeds height AND cell is not focused
 */
const showViewMoreInner = window.Vue.computed(() => {
    // Don't show view more button when cell is focused (auto-growing)
    if (isContentFocused.value) return false;
    return showViewMoreGradient.value;
});

/**
 * Update the content height in the store when the content height changes
 */
function updateContentHeight(): void {
    contentHeight.value = contentEl.value?.offsetHeight || 0;
    componentActionHeight.value = componentActionEl.value?.offsetHeight || 0;
    rowsStore.setContentHeight(props.profile, props.headerKey, contentHeight.value + componentActionHeight.value);
}

/**
 * To detect when the dimensions of the cell changes,
 * like when a different tab is selected
 */
function initResizeObserver(): void {
    const resizeObserver = new ResizeObserver(() => updateContentHeight());
    resizeObserver.observe(contentEl.value as HTMLElement);
}

/**
 * When the component is mounted, initialise the resize observer
 * and update the content height after a tick to ensure the content is rendered
 */
window.Vue.onMounted(() => {
    initResizeObserver();
    // Wait a tick to ensure the content has been rendered before measuring its height
    setTimeout(updateContentHeight, 0);
});

const animationDuration = window.Vue.ref("0.3s");

// #endregion cell height and view more handling
</script>

<template>
    <div
        class="grid-cell"
        :class="{
            'grid-cell--content-focused': isContentFocused,
        }"
        :style="{
            height: `${(rowsStore as any).rowDisplayHeights?.[props.profile.student.id] || 250}px`,
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
                <div class="show-more-container" :data-visible="showViewMoreGradient">
                    <button
                        :data-test="`mdp-grid-view-more-button-${props.profile.student.id}-${props.headerKey}`"
                        :data-visible="showViewMoreInner"
                        :tabindex="showViewMoreInner ? 0 : -1"
                        @click.prevent="rowsStore.toggleViewMore(props.profile.student.id)"
                    >
                        View More
                    </button>
                </div>
            </div>
            <div v-else class="content-overlay empty-state no-print">
                <empty-state icon="icon-textbox">
                    <button @click.prevent="onAdd">Add {{ headerStore.getTitleByKey(props.headerKey) }}</button>
                </empty-state>
            </div>
            <div ref="componentActionEl">
                <mdp-grid-cell-action
                    :profile="props.profile"
                    :header-key="props.headerKey"
                    :has-changed="hasChanged"
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

    .show-more-container {
        background: linear-gradient(0deg, #fff 0%, #fff0 100%);
        display: flex;
        justify-content: end;
        opacity: 0;
        transition: opacity 0.3s;

        &[data-visible="true"] {
            opacity: 1;
        }

        button {
            margin: 5px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;

            &[data-visible="true"] {
                opacity: 1;
                pointer-events: all;
            }
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

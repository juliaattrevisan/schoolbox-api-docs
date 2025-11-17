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
const isFocused = window.Vue.ref(false);
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

// #region Cell height and view more handling

/**
 * The main container around this cell's content component
 * Used to measure the height of the content
 */
const contentEl = window.Vue.ref<HTMLElement | null>(null);
const contentHeight = window.Vue.ref(0);

/**
 * The main container around this cell's action component
 * Used to measure the height of the component action
 */
const componentActionEl = window.Vue.ref<HTMLElement | null>(null);
const componentActionHeight = window.Vue.ref(0);

/**
 *
 */
const contentIsEmpty = window.Vue.computed(() => {
    return currentText.value === null;
});

/**
 * Update the content height in the store when the content height changes
 */
function updateContentHeight(): void {
    contentHeight.value = contentEl.value?.offsetHeight || 0;
    componentActionHeight.value = componentActionEl.value?.offsetHeight || 0;

    rowsStore.setProfileContentHeight(
        props.profile,
        props.headerKey,
        contentHeight.value + componentActionHeight.value,
    );
}

/**
 * Whether to show the view more gradient and button
 */
const showViewMoreGradient = window.Vue.computed(() => {
    const store = rowsStore as unknown as { profileRowDisplayHeights?: Record<number, number> };
    const displayHeights = store.profileRowDisplayHeights;
    if (!displayHeights) return false;
    const displayHeight = displayHeights[props.profile.student.id];
    if (!displayHeight) return false;
    return contentHeight.value + componentActionHeight.value > displayHeight;
});

/**
 * Whether to show the view more button
 */
const showViewMoreInner = window.Vue.computed(() => {
    return showViewMoreGradient.value;
});

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

// #endregion Cell height and view more handling
</script>

<template>
    <div
        class="grid-cell"
        :style="{ height: `${(rowsStore as any).profileRowDisplayHeights?.[props.profile.student.id] || 250}px` }"
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
            <div v-if="!contentIsEmpty" class="content-overlay">
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
                    :is-focused="isFocused"
                    @modify="onModify"
                    @save="onSave"
                />
            </div>
        </div>
    </div>
</template>

<style lang="scss">
.grid-cell {
    overflow: clip; // Use clip to avoid focus scrolling the cell content
    position: relative;
    text-overflow: ellipsis;
    transition: height v-bind(animationDuration) ease;
}

.content-container-fixed {
    bottom: 0;
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

<script setup lang="ts">
/**
 * Display adjustment levels, either Adjustment Category or Adjustment Level
 */

import EmptyState from "@schoolboxVue/components/empty-state.vue";
import type { Profile, ProfileAdjustmentKeys, AdjustmentType } from "../../js/types/manageDifferentiationProfilesTypes";
import { useHeaderStore } from "../store/mdpHeaderStore";
import { useRowsStore, ANIMATION_DURATION } from "../store/mdpRowsStore";
import { useSaveStore } from "../store/mdpSaveStore";
import MdpGridCellAction from "./mdp-grid-cell-action.vue";
import MdpGridCellShowMore from "./mdp-grid-cell-show-more.vue";

const props = defineProps<{
    profile: Profile;
    headerKey: ProfileAdjustmentKeys;
    adjustments: number[] | null;
    options: AdjustmentType[];
}>();

const headerStore = useHeaderStore();
const rowsStore = useRowsStore();
const saveStore = useSaveStore();
const contentEl = window.Vue.ref<HTMLElement | null>(null);
const componentActionEl = window.Vue.ref<HTMLElement | null>(null);
const animationDuration = `${ANIMATION_DURATION}s`; // duration for animations in seconds

// #region modifying the selected adjustments

const selectedAdjustments = window.Vue.ref<number[]>(props.adjustments ?? []);
const hasChanged = window.Vue.computed(
    () => selectedAdjustments.value.join(",") !== (props.adjustments ?? []).join(","),
);
const isSaving = window.Vue.ref(false);

/**
 * Whether the content of the cell is focused
 */
const isContentFocused = window.Vue.computed(() => {
    const store = rowsStore as unknown as { focusedCells?: Record<number, string[]> };
    return (store.focusedCells?.[props.profile.student.id] ?? []).includes(props.headerKey);
});

/**
 * Whether the content of the cell is empty (and not focused)
 */
const isContentEmpty = window.Vue.computed(() => {
    // If this cell is focused, treat it as not empty
    if (isContentFocused.value) return false;
    // Otherwise, check if we have actual content
    return props.adjustments === null;
});

/**
 * When the modify button is clicked.
 */
function onModify(): void {
    rowsStore.updateFocusedCell(props.profile.student.id, props.headerKey, true);
}

// #endregion modifying the selected adjustments

// #region calculating cell height

const contentHeight = window.Vue.ref(0);
const componentActionHeight = window.Vue.ref(0);

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

// #endregion calculating cell height

/**
 * When the save button is clicked.
 */
async function onSave(): Promise<void> {
    isSaving.value = true;
    const { profile } = props;
    profile[props.headerKey] = selectedAdjustments.value.length > 0 ? selectedAdjustments.value : null;
    await saveStore.save(profile);
    rowsStore.updateFocusedCell(props.profile.student.id, props.headerKey, false);
    isSaving.value = false;
}
</script>

<template>
    <div
        class="grid-cell"
        :class="{
            'grid-cell--content-focused': isContentFocused,
        }"
        :style="{
            height: `${rowsStore.rowDisplayHeights?.[props.profile.student.id]}px`,
        }"
    >
        <div ref="contentEl">
            <div v-if="isContentFocused">
                <div class="checklist l-pad-s">
                    <legend class="t-visually-hidden">
                        {{ headerStore.getTitleByKey(props.headerKey) }} for {{ props.profile.student.givenName }}
                        {{ props.profile.student.lastName }}
                    </legend>
                    <ul class="option-list">
                        <li v-for="option in options" :key="option.id">
                            <input
                                :id="`checkbox-${option.id}-${headerKey}-${props.profile.student.id}`"
                                v-model="selectedAdjustments"
                                :value="option.id"
                                type="checkbox"
                            /><label :for="`checkbox-${option.id}-${headerKey}-${props.profile.student.id}`">{{
                                option.name
                            }}</label>
                        </li>
                    </ul>
                </div>
            </div>
            <div v-else class="l-pad-s">
                <ul>
                    <li v-for="adjustment in adjustments" :key="adjustment">
                        {{ options.find((option) => option.id === adjustment)?.name }}
                    </li>
                </ul>
            </div>
        </div>
        <div class="content-container-fixed">
            <div v-if="!isContentEmpty" class="content-overlay">
                <mdp-grid-cell-show-more
                    :profile="props.profile"
                    :header-key="props.headerKey"
                    :content-height="contentHeight"
                    :component-action-height="componentActionHeight"
                    :is-content-focused="isContentFocused"
                />
            </div>
            <div v-else class="content-overlay empty-state no-print">
                <empty-state icon="icon-list">
                    <button @click.prevent="onModify">Add {{ headerStore.getTitleByKey(props.headerKey) }}</button>
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

<style scoped lang="scss">
.grid-cell {
    overflow: clip; // Use clip to avoid focus scrolling the cell content
    position: relative;
    text-overflow: ellipsis;
    transition: height v-bind(animationDuration) ease;
    &--content-focused {
        .content-container-fixed .content-overlay {
            border: 1px solid var(--form-border-color) !important;
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
</style>

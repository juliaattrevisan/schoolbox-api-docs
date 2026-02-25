<script setup lang="ts">
/**
 * Display adjustment levels, either Adjustment Category or Adjustment Level
 */

import EmptyState from "@schoolboxVue/components/empty-state.vue";
import LgCellActionSave from "@schoolboxVue/components/grid/lazyGrid/lg-cell-action-save.vue";
import {
    useLazyGridRowsHeightStore,
    ANIMATION_DURATION,
} from "@schoolboxVue/components/grid/lazyGrid/store/lgRowsHeightStore";
import { useContentHeight } from "@schoolboxVue/components/grid/lazyGrid/composable/useContentHeight";
import LgCellShowMore from "@schoolboxVue/components/grid/lazyGrid/lg-cell-show-more.vue";
import type {
    Profile,
    ProfileAdjustmentKeys,
    AdjustmentType,
} from "../../../js/types/manageDifferentiationProfilesTypes";
import { useHeaderStore } from "../../store/mdpHeaderStore";
import { useSaveStore } from "../../store/mdpSaveStore";

const props = defineProps<{
    profile: Profile;
    headerKey: ProfileAdjustmentKeys;
    adjustments: AdjustmentType[] | null;
    options: AdjustmentType[];
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

// #region modifying the selected adjustments

const selectedAdjustments = window.Vue.ref<AdjustmentType[]>(
    // filter to only the latest available options
    (props.adjustments ?? []).filter((adj) => props.options.some((option) => option.id === adj.id)),
);
const hasChanged = window.Vue.computed(() => {
    // Compare against the filtered original adjustments (excluding removed options)
    const filteredOriginalAdjustments = (props.adjustments ?? []).filter((adj) =>
        props.options.some((option) => option.id === adj.id),
    );
    return (
        selectedAdjustments.value.map((a) => a.id).join(",") !== filteredOriginalAdjustments.map((a) => a.id).join(",")
    );
});
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
    return props.adjustments === null;
});

/**
 * When the modify button is clicked.
 */
function onModify(): void {
    lgRowsHeightStore.updateFocusedCell(props.profile.student.id, props.headerKey, true);
}

// #endregion modifying the selected adjustments

/**
 * Sort adjustments by the order they appear in props.options
 * @param adjustments - the adjustments to sort
 * @returns the sorted adjustments array
 */
function getSortedAdjustments(adjustments: AdjustmentType[]): AdjustmentType[] {
    return adjustments.length > 0
        ? adjustments.sort((a, b) => {
              const indexA = props.options.findIndex((option) => option.id === a.id);
              const indexB = props.options.findIndex((option) => option.id === b.id);
              return indexA - indexB;
          })
        : [];
}

/**
 * When the save button is clicked.
 */
async function onSave(): Promise<void> {
    isSaving.value = true;
    // If both original and new values are null/empty, don't trigger save
    if (props.adjustments === null && selectedAdjustments.value.length === 0) {
        lgRowsHeightStore.updateFocusedCell(props.profile.student.id, props.headerKey, false);
        isSaving.value = false;
        return;
    }
    const sortedAdjustments = getSortedAdjustments(selectedAdjustments.value);
    const newValue = sortedAdjustments.length > 0 ? sortedAdjustments : null;
    const toSave = {
        [props.headerKey]: newValue,
    };
    await saveStore.save(props.profile, toSave);
    lgRowsHeightStore.updateFocusedCell(props.profile.student.id, props.headerKey, false);
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
            height: `${lgRowsHeightStore.rowDisplayHeights?.[props.profile.student.id]}px`,
        }"
    >
        <div ref="contentEl">
            <div v-if="isContentFocused">
                <div v-if="props.options.length > 0" class="checklist l-pad-s">
                    <legend class="t-visually-hidden">
                        {{ headerStore.getTitleByKey(props.headerKey) }} for {{ props.profile.student.givenName }}
                        {{ props.profile.student.lastName }}
                    </legend>
                    <ul class="option-list">
                        <li v-for="option in options" :key="option.id">
                            <input
                                :id="`checkbox-${option.id}-${headerKey}-${props.profile.student.id}`"
                                v-model="selectedAdjustments"
                                :value="option"
                                type="checkbox"
                            /><label :for="`checkbox-${option.id}-${headerKey}-${props.profile.student.id}`">{{
                                option.name
                            }}</label>
                        </li>
                    </ul>
                </div>
                <div v-else class="l-pad-s">
                    <p><em>No available options to select.</em></p>
                </div>
            </div>
            <div v-else class="l-pad-s">
                <ul>
                    <li v-for="adjustment in selectedAdjustments" :key="adjustment.id">
                        {{ options.find((option) => option.id === adjustment.id)?.name }}
                    </li>
                </ul>
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
                <empty-state icon="icon-list">
                    <button @click.prevent="onModify">Add {{ headerStore.getTitleByKey(props.headerKey) }}</button>
                </empty-state>
            </div>
            <div ref="componentActionEl">
                <lg-cell-action-save
                    :id="props.profile.student.id"
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
            border: 1px solid var(--sbx-color-accent, var(--form-border-color)) !important;
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

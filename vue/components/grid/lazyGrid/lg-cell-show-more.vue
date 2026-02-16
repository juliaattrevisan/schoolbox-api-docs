<script setup lang="ts">
/**
 * Show more button component for lazy grid cells
 * Displays a gradient overlay and "View More" button when content exceeds the display height
 */

import { useLazyGridRowsHeightStore } from "./store/lgRowsHeightStore";

const props = defineProps<{
    rowId: number;
    headerKey: string;
    contentHeight: number;
    componentActionHeight: number;
    isContentFocused: boolean;
}>();

const lgRowsHeightStore = useLazyGridRowsHeightStore();

/**
 * Whether to show the view more gradient and button
 */
const showViewMoreGradient = window.Vue.computed(() => {
    const displayHeights = lgRowsHeightStore.rowDisplayHeights;
    if (!displayHeights) return false;
    const displayHeight = displayHeights[props.rowId];
    if (!displayHeight) return false;
    return props.contentHeight + props.componentActionHeight > displayHeight;
});

/**
 * Whether to show the view more button
 * Only show when content exceeds height AND cell is not focused
 */
const showViewMoreInner = window.Vue.computed(() => {
    // Don't show view more button when cell is focused (auto-growing)
    if (props.isContentFocused) return false;
    return showViewMoreGradient.value;
});

/**
 * Handle view more button click
 */
function onViewMoreClick(): void {
    lgRowsHeightStore.toggleViewMore(props.rowId);
}
</script>

<template>
    <div class="show-more-container" :data-visible="showViewMoreGradient">
        <button
            class="button sbx-button--smaller"
            :data-test="`lg-view-more-button-${props.rowId}-${props.headerKey}`"
            :data-visible="showViewMoreInner"
            :tabindex="showViewMoreInner ? 0 : -1"
            @click.prevent="onViewMoreClick"
        >
            View More
        </button>
    </div>
</template>

<style scoped lang="scss">
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
</style>

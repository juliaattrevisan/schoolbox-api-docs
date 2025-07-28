<script setup lang="ts">
/**
 * To load further rows when required
 */

import { toRef } from "vue";
import type { Metadata } from "@js/util/sbx/apiUtils";
import { usePagination } from "../composable/usePagination";

const props = defineProps<{
    metadata: Metadata;
}>();

const emit = defineEmits<{
    previous: [cursor: string];
    next: [cursor: string];
}>();

const { hasNext, toNext, hasPrevious, toPrevious, currentCursor, hasPaginate, reset } = usePagination(
    20,
    toRef(props.metadata),
);

/**
 * Handles the previous button click
 */
function onPreviousSelected(): void {
    toPrevious();
    emit("previous", currentCursor.value);
}

/**
 * Handles the next button click
 */
function onNextSelected(): void {
    toNext();
    emit("next", currentCursor.value);
}

/**
 *
 *
 */
window.Vue.watch(
    () => props.metadata,
    () => {
        console.warn("Metadata changed:", props.metadata);
    },
);
</script>

<template>
    <div class="pr-table-footer l-flex-row l-flex--align-center l-flex--justify-end l-gap-m l-pad-m">
        <!-- <p class="f-no-margins">
            <b
                >{{ (props.loadStatus.loadIndex - 1) * props.loadStatus.maxRows + 1 }}-{{
                    Math.min(props.loadStatus.loadIndex * props.loadStatus.maxRows, props.metadata.count)
                }}
                of {{ props.metadata.count }}</b
            >
        </p> -->
        <nav v-if="hasPaginate" class="l-flex-row l-gap-s">
            <button v-if="hasPrevious" type="button" title="Previous" @click="onPreviousSelected">&lt;</button>
            <button v-if="hasNext" type="button" title="Next" @click="onNextSelected">&gt;</button>
        </nav>
    </div>
</template>

<style scoped>
.pr-table-footer {
    background-color: white;
    left: 0;
    position: sticky;
}
</style>

<script setup lang="ts">
/**
 * To load further rows when required
 */

import { toRef } from "vue";
import type { Metadata } from "@js/util/sbx/apiUtils";
import { usePagination } from "../composable/usePagination";

const PER_PAGE = 20;

const props = defineProps<{
    endpoint: string;
    metadata: Metadata;
}>();

const emit = defineEmits<{
    previous: [cursor: string];
    next: [cursor: string];
}>();

const { cursorIndex, hasNext, toNext, hasPrevious, toPrevious, currentCursor, hasPaginate, reset } = usePagination(
    toRef(() => props.endpoint),
    PER_PAGE,
    toRef(() => props.metadata),
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
</script>

<template>
    <div class="pr-table-footer l-flex-row l-flex--align-center l-flex--justify-end l-gap-m l-pad-m">
        <p class="f-no-margins">
            <b
                >{{ cursorIndex * PER_PAGE + 1 }}-{{ Math.min((cursorIndex + 1) * PER_PAGE, props.metadata.count) }} of
                {{ props.metadata.count }}
            </b>
        </p>
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

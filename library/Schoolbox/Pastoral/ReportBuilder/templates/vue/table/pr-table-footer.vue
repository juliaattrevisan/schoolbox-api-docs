<script setup lang="ts">
/**
 * To load further rows when required
 */

import { toRef } from "vue";
import type { Metadata } from "@js/util/sbx/apiUtils";
import { usePagination } from "../composable/usePagination";

const props = defineProps<{
    url: string;
    metadata: Metadata;
    limit: number;
    isLoading: boolean; // if the table is currently loading, hide the pagination controls
}>();

const emit = defineEmits<{
    previous: [cursor: string];
    next: [cursor: string];
}>();

const { cursorIndex, hasNext, toNext, hasPrevious, toPrevious, currentCursor, hasPaginate } = usePagination(
    toRef(() => props.url),
    props.limit,
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
    <div
        v-if="!props.isLoading"
        class="pr-table-footer u-flex-row u-flex--align-center u-flex--justify-end u-gap-m l-pad-m l-c-cr-bottom"
    >
        <p class="f-no-margins">
            <b
                >{{ cursorIndex * props.limit + 1 }}-{{
                    Math.min((cursorIndex + 1) * props.limit, props.metadata.count)
                }}
                of
                {{ props.metadata.count }}
            </b>
        </p>
        <nav v-if="hasPaginate" class="u-flex-row u-gap-m">
            <button
                :disabled="!hasPrevious"
                type="button"
                title="Previous"
                aria-label="Previous"
                @click="onPreviousSelected"
            >
                &lt;
            </button>
            <button :disabled="!hasNext" type="button" title="Next" aria-label="Next" @click="onNextSelected">
                &gt;
            </button>
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

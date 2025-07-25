<script setup lang="ts">
/**
 * To load further rows when required
 */

import type { Metadata } from "@js/util/sbx/apiUtils";

const props = defineProps<{
    metadata: Metadata;
    hasMoreRows: boolean;
}>();

const emit = defineEmits<{
    previous: [];
    next: [];
}>();

/**
 * Handles the previous button click
 */
function onPreviousSelected(): void {
    emit("previous");
}

/**
 * Handles the next button click
 */
function onNextSelected(): void {
    emit("next");
}
</script>

<template>
    <div class="pr-table-footer l-flex-row l-flex--align-center l-flex--justify-end l-gap-m l-pad-m">
        <p class="f-no-margins">
            <b>1-25 of {{ props.metadata.count }}</b>
        </p>
        <nav v-if="props.hasMoreRows" class="l-flex-row l-gap-s">
            <button type="button" @click="onPreviousSelected">&lt;</button>
            <button type="button" @click="onNextSelected">&gt;</button>
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

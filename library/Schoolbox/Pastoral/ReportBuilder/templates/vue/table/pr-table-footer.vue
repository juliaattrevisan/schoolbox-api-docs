<script setup lang="ts">
/**
 * To load further rows when required
 */

import type { Metadata } from "@js/util/sbx/apiUtils";
import type { LoadStatus } from "../composable/useLoadPrTable";

const props = defineProps<{
    metadata: Metadata;
    loadStatus: LoadStatus;
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
            <b
                >{{ (props.loadStatus.loadIndex - 1) * props.loadStatus.maxRows + 1 }}-{{
                    Math.min(props.loadStatus.loadIndex * props.loadStatus.maxRows, props.metadata.count)
                }}
                of {{ props.metadata.count }}</b
            >
        </p>
        <nav v-if="props.loadStatus.hasMoreRows" class="l-flex-row l-gap-s">
            <button v-if="props.loadStatus.loadIndex > 1" type="button" title="Previous" @click="onPreviousSelected">
                &lt;
            </button>
            <button
                v-if="props.loadStatus.loadIndex < props.loadStatus.maxLoads"
                type="button"
                title="Next"
                @click="onNextSelected"
            >
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

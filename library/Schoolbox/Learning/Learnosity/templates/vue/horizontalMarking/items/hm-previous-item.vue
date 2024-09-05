<script setup lang="ts">
/**
 * To move to the previous item (card) if available
 */

import { useStore } from "../store/horizontalMarkingStore";
import { useItemsStore } from "../store/hmItemsStore";

const store = useStore();
const itemsStore = useItemsStore();

/**
 * To move to previous item
 */
function onSelected(): void {
    itemsStore.toPreviousItem();
}
</script>

<template>
    <button
        v-if="itemsStore.hasPreviousItem"
        class="button full-width icon-previous"
        :disabled="!itemsStore.hasPreviousItem || store.isProcessing"
        @click.prevent="onSelected"
    >
        Previous item
    </button>
</template>

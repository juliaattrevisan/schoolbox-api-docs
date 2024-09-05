<script setup lang="ts">
/**
 * To move to the next item (card) if available
 */

import { useStore } from "../store/horizontalMarkingStore";
import { useItemsStore } from "../store/hmItemsStore";

const store = useStore();
const itemsStore = useItemsStore();

/**
 * The title of the button, will change when next is attempt comments
 */
const buttonTitle = window.Vue.computed(() => {
    return itemsStore.isNextFinalReview ? "Overall feedback" : "Next item";
});

/**
 * To move to next item
 */
function onSelected(): void {
    itemsStore.toNextItem();
}
</script>

<template>
    <button
        v-if="!itemsStore.isAttemptComment"
        class="button full-width icon-next"
        :style="`${itemsStore.isNextFinalReview ? 'font-weight: bold' : ''}`"
        :disabled="!store.isLoaded || store.isProcessing || !itemsStore.hasNextItem"
        @click.prevent="onSelected"
    >
        {{ buttonTitle }}
    </button>
</template>

<script setup lang="ts">
/**
 * To move to the next item (card) if available
 */

import { useStore } from "../store/horizontalMarkingStore";
import { useItemsStore } from "../store/hmItemsStore";

const store = useStore();
const itemsStore = useItemsStore();

/**
 * Hide the button if there is no next item
 */
const opacity = window.Vue.computed(() => {
    return itemsStore.isAttemptComment ? 0 : 1;
});

/**
 * The title of the button, will change when next is attempt comments
 */
const buttonTitle = window.Vue.computed(() => {
    return itemsStore.isNextFinalReview ? "Attempt Comments" : "Next Card";
});

/**
 * To move to next item
 */
function onSelected() {
    itemsStore.toNextItem();
}
</script>

<template>
    <button
        class="button full-width icon-next"
        :disabled="!store.isLoaded || !itemsStore.hasNextItem"
        @click.prevent="onSelected"
    >
        {{ buttonTitle }}
    </button>
</template>

<style scoped>
.button {
    opacity: v-bind(opacity);
}
</style>

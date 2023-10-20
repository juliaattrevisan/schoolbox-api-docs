<script setup lang="ts">
/**
 * To move to the next item (card) if available
 */

import { useItemsStore } from "../store/hmItemsStore";

const itemsStore = useItemsStore();

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
    <button class="button full-width icon-next" :disabled="!itemsStore.hasNextItem" @click.prevent="onSelected">
        {{ buttonTitle }}
    </button>
</template>

<style scoped></style>

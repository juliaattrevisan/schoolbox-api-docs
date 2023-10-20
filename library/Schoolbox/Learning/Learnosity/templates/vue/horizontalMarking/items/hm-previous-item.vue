<script setup lang="ts">
/**
 * To move to the previous item (card) if available
 */

import { useItemsStore } from "../store/hmItemsStore";

const itemsStore = useItemsStore();

/**
 * Hide the button if there is no previous item
 */
const opacity = window.Vue.computed(() => {
    return itemsStore.hasPreviousItem ? 1 : 0;
});

/**
 * To move to previous item
 */
function onSelected() {
    itemsStore.toPreviousItem();
}
</script>

<template>
    <button class="button full-width icon-previous" :disabled="!itemsStore.hasPreviousItem" @click.prevent="onSelected">
        Previous Card
    </button>
</template>

<style scoped>
.button {
    opacity: v-bind(opacity);
}
</style>

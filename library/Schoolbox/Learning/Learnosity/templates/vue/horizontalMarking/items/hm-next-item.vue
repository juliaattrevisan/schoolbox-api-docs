<script setup lang="ts">
/**
 * To move to the next item (card) if available
 */

import { useItemsStore } from "../store/hmItemsStore";

const itemsStore = useItemsStore();

/**
 * To move to next item
 */
function onSelected() {
    itemsStore.toNextItem();
}
</script>

<template>
    <button class="button full-width" :disabled="!itemsStore.hasNextItem" @click.prevent="onSelected">Next Card</button>
</template>

<style scoped></style>

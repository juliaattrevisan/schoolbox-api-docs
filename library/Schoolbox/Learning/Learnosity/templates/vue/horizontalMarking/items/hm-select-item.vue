<script setup lang="ts">
/**
 * To move between the items (called cards in sbx)
 */

import { useItemsStore } from "../store/hmItemsStore";

const itemsStore = useItemsStore();

/**
 * To change the active card
 * @param {Event} event - the event from the select
 */
function onChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    itemsStore.setActiveItem(target.value);
}
</script>

<template>
    <select @change="onChange">
        <option
            v-for="item in itemsStore.items"
            :key="item.id"
            :value="item.reference"
            :selected="itemsStore.activeItem.reference === item.reference"
        >
            {{ item.title }}
        </option>
    </select>
</template>

<style scoped></style>

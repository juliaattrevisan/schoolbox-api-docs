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
    <div>
        <h2 class="subheader">Cards</h2>
        <select @change="onChange">
            <option
                v-for="card in itemsStore.items"
                :key="card.id"
                :value="card.reference"
                :selected="itemsStore.activeItem.reference === card.reference"
            >
                {{ card.title }}
            </option>
        </select>
    </div>
</template>

<style scoped></style>

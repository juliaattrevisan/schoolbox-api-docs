<script setup lang="ts">
/**
 * To move between the items (called cards in sbx)
 */

import { useCardsStore } from "./store/hmItemsStore";

const cardsStore = useCardsStore();

/**
 * To change the active card
 * @param {Event} event - the event from the select
 */
function onChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    cardsStore.setActiveCard(target.value);
}
</script>

<template>
    <div>
        <h2 class="subheader">Cards</h2>
        <select @change="onChange">
            <option
                v-for="card in cardsStore.cards"
                :key="card.id"
                :value="card.reference"
                :selected="cardsStore.activeCard.reference === card.reference"
            >
                {{ card.title }}
            </option>
        </select>
    </div>
</template>

<style scoped></style>

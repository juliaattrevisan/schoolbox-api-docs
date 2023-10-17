<script setup lang="ts">
/**
 * To move between the cards
 */

import { useCardsStore } from "./store/hmCardsStore";

const cardsStore = useCardsStore();
</script>

<template>
    <div>
        <h2 class="subheader">Cards</h2>
        <select>
            <option v-for="card in cardsStore.cards" :key="card.id">
                {{ card.title }}
            </option>
        </select>
    </div>
</template>

<style scoped></style>

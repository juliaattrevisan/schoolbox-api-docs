/**
 * Holds the cards (items in Learnosity) and the active card (the one being marked)
 */

import { defineStore } from "pinia";
import type { Card } from "../types/Card";

export const EMPTY_CARD: Card = { id: "", reference: "", title: "" };

export const useCardsStore = defineStore("hmCards", () => {
    const cards = window.Vue.ref<Card[]>([]);
    const activeCard = window.Vue.ref<Card>(EMPTY_CARD);

    /**
     * store any passed through properties
     * @param {Card[]} $cards - the cards to store
     */
    function init($cards: Card[]) {
        cards.value = $cards;
        activeCard.value = cards.value[0];
    }

    return { init, cards, activeCard };
});

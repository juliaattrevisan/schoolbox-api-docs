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
        activeCard.value = { ...$cards[0] };
    }

    /**
     * Set the active card
     * @param {string} reference - the reference of the card to set as active
     */
    function setActiveCard(reference: string) {
        activeCard.value = cards.value.find((card) => card.reference === reference) || EMPTY_CARD;
    }

    return { init, cards, activeCard, setActiveCard };
});

/**
 * Holds the items (card in sbx) and the active item (the one being marked)
 */

import { defineStore } from "pinia";
import type { Item } from "../types/Item";

export const useItemsStore = defineStore("hmItems", () => {
    const items = window.Vue.ref<Item[]>([]);
    const activeItem = window.Vue.ref<Item | null>(null);
    const activeItemIndex = window.Vue.ref<number>(0);
    const hasPreviousItem = window.Vue.computed(() => activeItemIndex.value > 0);
    const hasNextItem = window.Vue.computed(() => activeItemIndex.value < items.value.length - 1);
    const isNextFinalReview = window.Vue.computed(() => items.value[activeItemIndex.value + 1]?.isFinalReview);
    const isFinalReview = window.Vue.computed(() => activeItem.value?.isFinalReview);

    /**
     * store any passed through properties
     * @param {Item[]} $items - the items to store
     */
    function init($items: Item[]) {
        items.value = $items;
        items.value.forEach((item) => {
            item.questions = []; // add the questions array for future use
            item.isFinalReview = false;
        });
        // we add in an 'dummy' item for the final 'item' which allows the opportunity to provide final feedback for each student
        const lastItemForReview = {
            id: "0",
            reference: "final-review",
            title: "Attempt Comments",
            questions: [],
            isFinalReview: true,
        };
        items.value.push(lastItemForReview);
        activeItem.value = { ...$items[0] };
    }

    /**
     * Set the active card
     * @param {string} reference - the reference of the card to set as active
     */
    function setActiveItem(reference: string) {
        activeItem.value = items.value.find((card) => card.reference === reference) as Item;
        activeItemIndex.value = items.value.findIndex((card) => card.reference === reference);
    }

    /**
     * Set the previous card
     */
    function toPreviousItem() {
        if (hasPreviousItem.value) {
            activeItemIndex.value -= 1;
            activeItem.value = items.value[activeItemIndex.value];
        }
    }

    /**
     * Set the next card
     */
    function toNextItem() {
        if (hasNextItem.value) {
            activeItemIndex.value += 1;
            activeItem.value = items.value[activeItemIndex.value];
        }
    }

    return {
        items,
        activeItem,
        hasPreviousItem,
        hasNextItem,
        isNextFinalReview,
        isFinalReview,
        init,
        setActiveItem,
        toPreviousItem,
        toNextItem,
    };
});

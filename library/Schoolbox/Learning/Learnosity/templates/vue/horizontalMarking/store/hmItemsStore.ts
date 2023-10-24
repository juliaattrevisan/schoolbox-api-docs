/**
 * Holds the items (card in sbx) and the active item (the one being marked)
 */

import { defineStore } from "pinia";
import type { Item } from "../types/Item";

export const useItemsStore = defineStore("hmItems", () => {
    const items = window.Vue.ref<Item[]>([]);
    const itemsWithQuestions = window.Vue.computed(() =>
        items.value.filter((item) => item.questions.length > 0 || item.isFinalReview)
    ); // only show those items with questions
    const activeItem = window.Vue.ref<Item>({ id: "", reference: "", title: "", questions: [], isFinalReview: false });
    const activeItemIndex = window.Vue.ref<number>(0);
    const hasPreviousItem = window.Vue.computed(() => activeItemIndex.value > 0);
    const hasNextItem = window.Vue.computed(() => activeItemIndex.value < itemsWithQuestions.value.length - 1);
    const isNextFinalReview = window.Vue.computed(
        () => itemsWithQuestions.value[activeItemIndex.value + 1]?.isFinalReview
    );
    const isFinalReview = window.Vue.computed(() => activeItem.value.isFinalReview);

    /**
     * store any passed through properties
     * @param {Item[]} $items - the items to store
     */
    function init($items: Item[]) {
        $items.forEach((item) => {
            items.value.push({
                ...item,
                questions: [],
                isFinalReview: false,
            });
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
    }

    /**
     * Add questions to the items, unlike the items initialy loaded, the items linked to students loaded
     * via the reports api contain the questions, so we add them here
     * @param {Item[]} $items - the items to reference
     */
    function addQuestions($items: Item[]) {
        $items.forEach((item, index) => {
            items.value[index].questions = item.questions;
        });
        activeItem.value = { ...itemsWithQuestions.value[0] };
    }

    /**
     * Set the active card
     * @param {string} reference - the reference of the card to set as active
     */
    function setActiveItem(reference: string) {
        activeItem.value = itemsWithQuestions.value.find((card) => card.reference === reference) as Item;
        activeItemIndex.value = itemsWithQuestions.value.findIndex((card) => card.reference === reference);
    }

    /**
     * Set the previous card
     */
    function toPreviousItem() {
        if (hasPreviousItem.value) {
            activeItemIndex.value -= 1;
            activeItem.value = itemsWithQuestions.value[activeItemIndex.value];
        }
    }

    /**
     * Set the next card
     */
    function toNextItem() {
        if (hasNextItem.value) {
            activeItemIndex.value += 1;
            activeItem.value = itemsWithQuestions.value[activeItemIndex.value];
        }
    }

    return {
        items,
        itemsWithQuestions,
        activeItem,
        hasPreviousItem,
        hasNextItem,
        isNextFinalReview,
        isFinalReview,
        init,
        addQuestions,
        setActiveItem,
        toPreviousItem,
        toNextItem,
    };
});

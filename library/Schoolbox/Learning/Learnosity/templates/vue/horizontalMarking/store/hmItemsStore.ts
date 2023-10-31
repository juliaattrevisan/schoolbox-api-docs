/**
 * Holds the items (card in sbx) and the active item (the one being marked)
 */

import { defineStore } from "pinia";
import type { Item } from "@schoolboxLearnosity/js/types/Item";

export const useItemsStore = defineStore("hmItems", () => {
    const items = window.Vue.ref<Item[]>([]);
    const activeItem = window.Vue.ref<Item>({
        id: "",
        reference: "",
        title: "",
        questions: [],
        isAttemptComment: false,
    });
    const activeItemIndex = window.Vue.ref<number>(0);
    const hasPreviousItem = window.Vue.computed(() => activeItemIndex.value > 0);
    const hasNextItem = window.Vue.computed(() => activeItemIndex.value < items.value.length - 1);
    const isNextFinalReview = window.Vue.computed(() => items.value[activeItemIndex.value + 1]?.isAttemptComment);
    const isAttemptComment = window.Vue.computed(() => activeItem.value.isAttemptComment === true);
    const showMarkedAttempts = window.Vue.ref<boolean>(true);

    /**
     * store any passed through properties
     * @param {Item[]} $items - the items to store
     */
    function init($items: Item[]) {
        $items.forEach((item) => {
            items.value.push({
                ...item,
                questions: [],
                isAttemptComment: false,
            });
        });
        // we add in an 'dummy' item for the final 'item' which allows the opportunity to provide final feedback for each student
        const lastItemForReview = {
            id: "0",
            reference: "final-review",
            title: "Attempt Comments",
            questions: [],
            isAttemptComment: true,
        };
        items.value.push(lastItemForReview);
    }

    /**
     * Add questions to the items, unlike the items initially loaded, the items linked to students loaded
     * via the reports api contain the questions, so we add them here
     * @param {Item[]} $items - the items to reference
     */
    function addQuestions($items: Item[]) {
        $items.forEach((item, index) => {
            items.value[index].questions = item.questions;
        });
        // only show those items with questions and the final review
        items.value = items.value.filter((item) => item.questions.length > 0 || item.isAttemptComment);
        activeItem.value = { ...items.value[0] };
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
     * Set the previous item
     */
    function toPreviousItem() {
        if (hasPreviousItem.value) {
            activeItemIndex.value -= 1;
            activeItem.value = items.value[activeItemIndex.value];
        }
    }

    /**
     * Set the next item
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
        isAttemptComment,
        showMarkedAttempts,
        init,
        addQuestions,
        setActiveItem,
        toPreviousItem,
        toNextItem,
    };
});

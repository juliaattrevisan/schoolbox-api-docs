/**
 * Holds the items and the active item (the one being marked)
 */

import { defineStore } from "pinia";
import type { Item } from "@schoolboxLearnosity/js/types/Item";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";

export const useItemsStore = defineStore("hmItems", () => {
    const items = window.Vue.ref<Item[]>([]);
    const itemReferenceList = window.Vue.ref<string[]>([]);
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
    const hasVariedAttempts = window.Vue.ref<boolean>(false); // if different students have attempted a different number of items/questions

    /**
     * store any passed through properties
     * @param {Item[]} $items - the items to store
     */
    function init($items: Item[]) {
        $items.forEach((item) => {
            items.value.push({
                ...item,
                isAttemptComment: false,
            });
        });
        itemReferenceList.value = items.value.map((item) => item.reference);
        // we add in an 'dummy' item for the final 'item' which allows the opportunity to provide final feedback for each student
        const lastItemForReview = {
            id: "0",
            reference: "attempt-comments",
            title: "Overall Comments",
            isAttemptComment: true,
        };
        items.value.push(lastItemForReview);
    }

    /**
     * Add questions to the items, unlike the items initially loaded, the items linked to students loaded
     * via the reports api contain the questions, so we add them here
     * @param {StudentItem[]} $studentItems - the items to reference
     * @param {boolean} isLastAttempt - if this is the last attempt that will be loaded
     */
    function addQuestions($studentItems: StudentItem[], isLastAttempt: boolean) {
        // to only get student items that are also in the store items, as a quiz could have been modified after the student attempted the quiz
        const validStudentItems = $studentItems.filter((item) => itemReferenceList.value.includes(item.reference));
        if (validStudentItems.length !== itemReferenceList.value.length) {
            hasVariedAttempts.value = true;
        }
        /**
         * we cant assume the items are coming in from the API in the same order as the items we have stored,
         * we'll need to match them up by reference
         */
        validStudentItems.forEach((studentItem) => {
            const matchedItem = items.value.find((storedItem) => storedItem.reference === studentItem.reference);
            // if the questions have already been added, we don't need to overwrite them
            if (matchedItem && !matchedItem.questions) {
                matchedItem.questions = studentItem.questions;
            }
        });
        if (isLastAttempt) {
            // only show those items with questions and the final review
            items.value = items.value.filter(
                (item) => (item.questions && item.questions.length > 0) || item.isAttemptComment
            );
            activeItem.value = { ...items.value[0] };
        }
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
        itemReferenceList,
        activeItem,
        hasPreviousItem,
        hasNextItem,
        isNextFinalReview,
        isAttemptComment,
        hasVariedAttempts,
        init,
        addQuestions,
        setActiveItem,
        toPreviousItem,
        toNextItem,
    };
});

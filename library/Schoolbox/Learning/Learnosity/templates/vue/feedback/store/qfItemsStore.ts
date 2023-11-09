/**
 * Holds the items (card in sbx) and the active item (the one being marked)
 */

import { defineStore } from "pinia";
import type { Item } from "@schoolboxLearnosity/js/types/Item";

export const useItemsStore = defineStore("hmItems", () => {
    const items = window.Vue.ref<Item[]>([]);

    /**
     * Add questions to the items, unlike the items initially loaded, the items linked to students loaded
     * via the reports api contain the questions, so we add them here
     * @param {Item[]} $items - the items to reference
     */
    function addItems($items: Item[]) {
        items.values = $items;
        // only show those items with questions and the final review
        items.value = items.value.filter((item) => item.questions.length > 0 || item.isAttemptComment);
    }

    return {
        items,
        addItems,
    };
});

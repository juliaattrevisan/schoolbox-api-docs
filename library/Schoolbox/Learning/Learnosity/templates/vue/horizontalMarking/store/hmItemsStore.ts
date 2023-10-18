/**
 * Holds the items (items in Learnosity) and the active card (the one being marked)
 */

import { defineStore } from "pinia";
import type { Item } from "../types/Item";

export const EMPTY_ITEM: Item = { id: "", reference: "", title: "" };

export const useItemsStore = defineStore("hmItems", () => {
    const items = window.Vue.ref<Item[]>([]);
    const activeItem = window.Vue.ref<Item>(EMPTY_ITEM);

    /**
     * store any passed through properties
     * @param {Item[]} $items - the items to store
     */
    function init($items: Item[]) {
        items.value = $items;
        activeItem.value = { ...$items[0] };
    }

    /**
     * Set the active card
     * @param {string} reference - the reference of the card to set as active
     */
    function setActiveItem(reference: string) {
        activeItem.value = items.value.find((card) => card.reference === reference) || EMPTY_ITEM;
    }

    /**
     * Set the active card
     * @param {string} reference - the reference of the card to set as active
     */
    function toNextItem(reference: string) {
        activeItem.value = items.value.find((card) => card.reference === reference) || EMPTY_ITEM;
    }

    return { init, items, activeItem, setActiveItem };
});

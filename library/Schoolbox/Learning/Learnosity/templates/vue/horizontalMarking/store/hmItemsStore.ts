/**
 * Holds the items (items in Learnosity) and the active card (the one being marked)
 */

import { defineStore } from "pinia";
import type { Item } from "../types/Item";

export const EMPTY_ITEM: Item = { id: "", reference: "", title: "" };

export const useItemsStore = defineStore("hmItems", () => {
    const items = window.Vue.ref<Item[]>([]);
    const activeItem = window.Vue.ref<Item>(EMPTY_ITEM);
    const activeItemIndex = window.Vue.ref<number>(0);
    const hasPreviousItem = window.Vue.computed(() => activeItemIndex.value > 0);
    const hasNextItem = window.Vue.computed(() => activeItemIndex.value < items.value.length - 1);

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

    return { init, items, activeItem, setActiveItem, hasPreviousItem, hasNextItem, toPreviousItem, toNextItem };
});

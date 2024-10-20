/**
 * To manage the state of a selected items list
 * Items are uniquely identified by their id
 */

import type { Ref, ComputedRef } from "vue";

export type Item = {
    id: number;
    name: string;
};

/**
 * To manage the state of a selected items list
 * @param $selected - to optionally pass in a reference to the selected list
 * @returns - selected, isEmpty, hasItems, add, remove, update, empty
 */
export function useSelected($selected: Ref<Item[]> | undefined = undefined): {
    selected: Ref<Item[]>;
    isEmpty: ComputedRef<boolean>;
    hasItems: ComputedRef<boolean>;
    add: (toAdd: Item | Item[]) => void;
    remove: (toRemove: Item | Item[]) => void;
    update: (updated: Item[]) => void;
    empty: () => void;
} {
    const selected = $selected === undefined ? window.Vue.ref<Item[]>([]) : $selected; // create a new selected list if one is not passed in
    const isEmpty = window.Vue.computed(() => selected.value.length === 0); // whether the selected list is empty
    const hasItems = window.Vue.computed(() => selected.value.length > 0); // whether the selected list has items

    /**
     * remove the item or items from the selected list
     * @param toRemove - the item or items to remove from the selected list
     */
    function remove(toRemove: Item | Item[]): void {
        let currentSelected = selected.value; // get the current selected list to modify
        const itemsToRemove = Array.isArray(toRemove) ? toRemove : [toRemove];
        itemsToRemove.forEach((itemToRemove) => {
            currentSelected = currentSelected.filter((selectedItem) => selectedItem.id !== itemToRemove.id);
        });
        selected.value = [...currentSelected]; // update the selected list
    }

    /**
     * add the passed in items to the selected list
     * @param toAdd - the item or items to add to the selected list
     */
    function add(toAdd: Item | Item[]): void {
        let currentSelected = selected.value; // get the current selected list to modify
        const itemsToAdd = Array.isArray(toAdd) ? toAdd : [toAdd];
        itemsToAdd.forEach((itemToAdd) => {
            const exists = selected.value.find((selectedItem) => selectedItem.id === itemToAdd.id); // check if the item already exists, preventing duplicates
            if (!exists) {
                currentSelected = [...currentSelected, itemToAdd];
            }
        });
        selected.value = [...currentSelected]; // update the selected list
    }

    /**
     * completely update the items with a new list
     * @param updated - the new list of items
     */
    function update(updated: Item[]): void {
        selected.value = [...updated];
    }

    /**
     * clear all the currently selected items
     */
    function empty(): void {
        selected.value = [];
    }

    return {
        selected,
        isEmpty,
        hasItems,
        add,
        remove,
        update,
        empty,
    };
}

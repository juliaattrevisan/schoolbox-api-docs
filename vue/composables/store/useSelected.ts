/**
 * To manage the state of any selected items
 */

import type { Ref, ComputedRef } from "vue";

type Item = {
    id: number;
    name: string;
};

/**
 * To manage the state of the selected items
 * @returns - the functions to manage the selected items
 */
export function useSelected(): {
    selected: Ref<Item[]>;
    isEmpty: ComputedRef<boolean>;
    hasItems: ComputedRef<boolean>;
    add: (toAdd: Item | Item[]) => void;
    remove: (toRemove: Item | Item[]) => void;
    switchItem: (item: Item, isChecked: boolean) => void;
    update: (updated: Item[]) => void;
    empty: () => void;
} {
    const selected = window.Vue.ref<Item[]>([]); // the items selected
    const isEmpty = window.Vue.computed(() => selected.value.length === 0); // whether the selected list is empty
    const hasItems = window.Vue.computed(() => selected.value.length > 0); // whether the selected list has items

    /**
     * remove the item or items from the selected list
     * @param toRemove - the item or items to remove from the selected list
     */
    function remove(toRemove: Item | Item[]): void {
        const itemsToRemove = Array.isArray(toRemove) ? toRemove : [toRemove];
        itemsToRemove.forEach((itemToRemove) => {
            const exists = selected.value.find((selectedItem) => selectedItem.id === itemToRemove.id);
            if (exists) {
                selected.value = selected.value.filter((selectedItem) => selectedItem.id !== itemToRemove.id);
            }
        });
    }

    /**
     * add the passed in items to the selected list
     * @param toAdd - the item or items to add to the selected list
     * @param removeIfExists - whether to remove the item if it already exists, to typically prevent duplicates
     */
    function add(toAdd: Item | Item[], removeIfExists: boolean = true): void {
        const itemsToAdd = Array.isArray(toAdd) ? toAdd : [toAdd];
        itemsToAdd.forEach((itemToAdd) => {
            const exists = selected.value.find((selectedItem) => selectedItem.id === itemToAdd.id);
            if (removeIfExists && exists) {
                remove(exists);
            } else {
                selected.value = [...selected.value, itemToAdd];
            }
        });
    }

    /**
     * to switch a items selection status
     * @param item - the item to switch the selection status of
     * @param isChecked - whether the item is checked or not
     */
    function switchItem(item: Item, isChecked: boolean): void {
        if (isChecked) {
            add(item);
        } else {
            remove(item);
        }
    }

    /**
     * completely update the items with a new array
     * @param updated - the new array of items
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
        switchItem,
        update,
        empty,
    };
}

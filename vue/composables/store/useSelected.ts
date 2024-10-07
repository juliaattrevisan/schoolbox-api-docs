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
    switchItem: (course: Item, isChecked: boolean) => void;
    update: (updated: Item[]) => void;
    empty: () => void;
} {
    const selected = window.Vue.ref<Item[]>([]); // the items selected
    const isEmpty = window.Vue.computed(() => selected.value.length === 0); // whether the selected list is empty
    const hasItems = window.Vue.computed(() => selected.value.length > 0); // whether the selected list has items

    /**
     * store any passed through properties
     * @param toAdd - the course or courses to add to the selected list
     */
    function add(toAdd: Item | Item[]): void {
        if (Array.isArray(toAdd)) {
            selected.value = [...selected.value, ...toAdd];
        } else {
            selected.value = [...selected.value, toAdd];
        }
    }

    /**
     * remove the course or courses from the selected list
     * @param toRemove - the course or courses to remove from the selected list
     */
    function remove(toRemove: Item | Item[]): void {
        if (Array.isArray(toRemove)) {
            toRemove.forEach((courseToRemove) => {
                selected.value = selected.value.filter((course) => course.id !== courseToRemove.id);
            });
        } else {
            selected.value = selected.value.filter((course) => course.id !== toRemove.id);
        }
    }

    /**
     * to switch a courses selection status
     * @param course - the course to switch the selection status of
     * @param isChecked - whether the course is checked or not
     */
    function switchItem(course: Item, isChecked: boolean): void {
        if (isChecked) {
            add(course);
        } else {
            remove(course);
        }
    }

    /**
     * completely update the courses with a new array
     * @param updated - the new array of courses
     */
    function update(updated: Item[]): void {
        selected.value = [...updated];
    }

    /**
     * clear all the currently selected courses
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

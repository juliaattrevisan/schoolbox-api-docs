import type { Ref } from "vue";

/**
 * Composable for managing a selected list state
 * @param items - Reactive ref containing the list of all items
 * @returns Object containing checkbox list state and computed properties
 */
export function useSelectedList<T>(items: Ref<T[]>): {
    showCheckboxes: Ref<boolean>;
    selectedList: Ref<T[]>;
    allSelected: Ref<boolean>;
    isIndeterminate: Ref<boolean>;
} {
    const selectedList = window.Vue.ref<T[]>([]) as Ref<T[]>; // currently selected items (checkboxes in the list)

    window.Vue.watch(items, (currentItems) => {
        selectedList.value = selectedList.value.filter((item) => currentItems.includes(item));
    });

    /**
     * Show checkboxes if there are more than one item
     */
    const showCheckboxes = window.Vue.computed(() => items.value.length > 1);

    /**
     * Check if all items are selected
     */
    const allSelected = window.Vue.computed({
        get: () => items.value.every((item) => selectedList.value.includes(item)),
        set: (value) => {
            selectedList.value = value ? items.value : [];
        },
    });

    /**
     * Function to check if the selection is indeterminate (some but not all selected)
     * to set the checkbox state appropriately
     */
    const isIndeterminate = window.Vue.computed(() => {
        return selectedList.value.length > 0 && selectedList.value.length < items.value.length;
    });

    return {
        showCheckboxes,
        selectedList,
        allSelected,
        isIndeterminate,
    };
}

/**
 * To add and remove items from the selected items
 */

import type { Ref } from "vue";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";

/**
 * To update the selected items
 * @param isSingleSelect - whether the search is single select
 * @param selected - the selected items
 * @param update - to update the selected items
 * @param add - to add items to the selected items
 * @param remove - to remove items from the selected items
 * @returns - the functions to add and remove items
 */
export function useOnSelectedChange(
    isSingleSelect: Ref<boolean>,
    selected: Ref<SearchSelectItem[]>,
    add: (items: SearchSelectItem[]) => void,
    remove: (items: SearchSelectItem[]) => void,
    update: (items: SearchSelectItem[]) => void,
): {
    onAdd: (items: SearchSelectItem[]) => void;
    onRemove: (items: SearchSelectItem[]) => void;
} {
    /**
     * To add items
     * @param items - the items selected
     */
    function onAdd(items: SearchSelectItem[]): void {
        // if single select, switch with the previous selection
        if (isSingleSelect.value && selected.value.length > 0) {
            update(items);
            return;
        }
        add(items);
    }

    /**
     * To remove items
     * @param items - the items deselected
     */
    function onRemove(items: SearchSelectItem[]): void {
        remove(items);
    }

    return { onAdd, onRemove };
}

<script setup lang="ts">
/**
 * Toggle the selection of currently listed search results so can select
 * multiple search results at once
 */

import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";

interface Props {
    results: SearchSelectItem[]; // the results returned from the search
    selected: SearchSelectItem[]; // the selected items
}
const props = defineProps<Props>();

const emit = defineEmits<{
    add: [items: SearchSelectItem[]];
    remove: [items: SearchSelectItem[]];
}>();

/**
 * To determine the behaviour of the 'Select all' button, whether to
 * add all results or remove all results
 * @returns - whether all results are selected
 */
const isAllResultsSelected = window.Vue.computed(() => {
    if (props.selected.length > 0) {
        const alreadySelected = props.results.filter((resultItem) => {
            return props.selected.some((node) => resultItem.id === node.id);
        });
        return alreadySelected.length === props.results.length;
    }
    return false;
});

/**
 * To add the 'indeterminate' class to the select all checkbox to show some
 * in the search results are already selected
 * @returns - whether some results are selected
 */
const isSomeResultsSelected = window.Vue.computed(() => {
    if (props.selected.length > 0) {
        const alreadySelected = props.results.filter((resultItem) => {
            return props.selected.some((node) => resultItem.id === node.id);
        });
        return alreadySelected.length >= 1 && alreadySelected.length !== props.results.length;
    }
    return false;
});

/**
 * To determine the check display of the checkbox
 * @returns - whether it's checked
 */
const isSelected = window.Vue.computed(() => isAllResultsSelected.value);

/**
 * toggle all depending on current check status
 * @param event - the click event
 */
function onClick(event?: MouseEvent): void {
    // ignore input label clicks, which get picked up due to the click listener on the parent div
    if (event && event.target && (event.target as HTMLElement).nodeName === "LABEL") {
        return;
    }
    if (isSelected.value) {
        emit("remove", props.results);
    } else {
        emit("add", props.results);
    }
}
</script>

<template>
    <li ref="resultEl" class="ss-results-item" data-test="ss-results-select-all">
        <div class="checklist sbx-checkbox-radio-container">
            <input
                id="checkbox-select-all"
                class="f-no-margins"
                type="checkbox"
                :checked="isSelected"
                :class="{ indeterminate: isSomeResultsSelected }"
                @click.stop="onClick"
            />
            <label for="checkbox-select-all" class="f-no-margins">Select all</label>
        </div>
    </li>
</template>

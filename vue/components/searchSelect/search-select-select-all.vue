<script setup lang="ts">
/**
 * Toggle the selection of currently listed search results so can select
 * multiple search results at once
 */

import type { Result } from "./types/Result";

interface Props {
    results: Result[]; // the results returned from the search
    selected: Result[]; // the selected results
}
const props = defineProps<Props>();

const emit = defineEmits<{
    addAll: [];
    removeAll: [];
}>();

/**
 * To determine the behaviour of the 'Select all' button, whether to
 * add all codes or remove all codes
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
const isChecked = window.Vue.computed(() => isAllResultsSelected.value);

/**
 * toggle all depending on current check status
 */
function onSelectAll(): void {
    if (isChecked.value) {
        emit("removeAll");
    } else {
        emit("addAll");
    }
}
</script>

<template>
    <button class="search-result" @click.prevent="onSelectAll">
        <div class="checklist">
            <input
                id="checkbox-select-all"
                type="checkbox"
                :checked="isChecked"
                :class="{ indeterminate: isSomeResultsSelected }"
            />
            <label for="checkbox-select-all"></label>
        </div>
        <strong>Select All</strong>
    </button>
</template>

<style lang="scss" scoped>
.search-result {
    background: white;
    display: flex;
    font-size: 0.825rem;
    margin: 0;
    padding: 0.5rem 0.75rem;
    text-align: left;
    transition: background 0.2s;
    width: 100%;
    &:hover {
        background: var(--content-ui-hover);
    }
}

.checklist {
    flex: 2rem 0 0; //ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it
    margin: 0;
}

input[type="checkbox"]:not(.plain) + label {
    margin: 0;
}
</style>

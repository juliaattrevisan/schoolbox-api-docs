<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Toggle the selection of currently listed search results so can select
 * multiple search results at once
 */

import { useSearchStore } from "../store/mgSearchStore";

const searchStore = useSearchStore();

/**
 * To determine the behaviour of the 'Select all' button, whether to
 * add all results or remove all results
 *
 * @returns { Boolean }
 */
const isAllResultsSelected = window.Vue.computed(() => {
    if (searchStore.selectedResults.length > 0) {
        const alreadySelectedResults = searchStore.filteredResults.filter((resultItem) => {
            return searchStore.selectedResults.some((selectionItem) => resultItem.id === selectionItem.id);
        });
        return alreadySelectedResults.length === searchStore.filteredResults.length ? true : false;
    } else {
        return false;
    }
});

/**
 * To add the 'indeterminate' class to the select all checkbox to show some
 * in the search results are already selected
 *
 * @returns { Boolean }
 */
const isSomeResultsSelected = window.Vue.computed(() => {
    if (searchStore.selectedResults.length > 0) {
        const alreadySelectedResults = searchStore.filteredResults.filter((resultItem) => {
            return searchStore.selectedResults.some((selectionItem) => resultItem.id === selectionItem.id);
        });
        return alreadySelectedResults.length >= 1 &&
            alreadySelectedResults.length !== searchStore.filteredResults.length
            ? true
            : false;
    } else {
        return false;
    }
});

/**
 * To determine the check display of the checkbox
 *
 * @returns { Boolean }
 */
const isChecked = window.Vue.computed(() => (isAllResultsSelected.value ? true : false));

/**
 * Add or remove all current search results
 */
function onSelectAll() {
    isChecked.value ? searchStore.removeAllResults() : searchStore.addAllResults();
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
.search-result-active {
    background: var(--content-ui-hover);
}

input[type="checkbox"]:not(.plain) + label {
    margin: 0;
}
</style>

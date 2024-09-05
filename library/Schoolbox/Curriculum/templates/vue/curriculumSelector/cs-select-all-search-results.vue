<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Toggle the selection of currently listed search results so can select
 * multiple search results at once
 */

import { useSearchStore } from "./store/csSearchStore";
import { useSelectedNodesStore } from "./store/csSelectedNodesStore";

const searchStore = useSearchStore();
const selectedNodesStore = useSelectedNodesStore();

/**
 * To determine the behaviour of the 'Select all' button, whether to
 * add all codes or remove all codes
 *
 * @returns { Boolean }
 */
const isAllResultsSelected = window.Vue.computed(() => {
    if (selectedNodesStore.nodes.length > 0) {
        const alreadySelectedNodes = searchStore.results.filter((resultItem) => {
            return selectedNodesStore.nodes.some((node) => resultItem.id === node.id);
        });
        return alreadySelectedNodes.length === searchStore.results.length ? true : false;
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
    if (selectedNodesStore.nodes.length > 0) {
        const alreadySelectedNodes = searchStore.results.filter((resultItem) => {
            return selectedNodesStore.nodes.some((node) => resultItem.id === node.id);
        });
        return alreadySelectedNodes.length >= 1 && alreadySelectedNodes.length !== searchStore.results.length
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
 * toggle all depending on current check status
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

.checklist {
    flex: 2rem 0 0; //ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it
    margin: 0;
}

input[type="checkbox"]:not(.plain) + label {
    margin: 0;
}
</style>

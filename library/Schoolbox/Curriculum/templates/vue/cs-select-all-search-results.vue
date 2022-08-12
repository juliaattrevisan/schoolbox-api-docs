<script setup>

/**
 * Toggle the selection of currently listed search results so can select
 * multiple search results at once
 */

import { computed } from 'vue';
import { useSearchResultsStore } from './store/csSearchResultsStore'; 
import { useSelectedCodesStore } from './store/csSelectedCodesStore'; 


const searchResultsStore = useSearchResultsStore();
const selectedCodesStore = useSelectedCodesStore();

/**
 * To determine the behaviour of the 'Select all' button, whether to 
 * add all codes or remove all codes
 * 
 * @returns { Boolean } 
 */
const isAllResultsSelected = computed(() => {
    if (selectedCodesStore.codes.length > 0) {
        const alreadySelectedCodes = searchResultsStore.results.filter((resultItem) => {
            return selectedCodesStore.codes.some((code) => resultItem.id === code.id);
        }); 
        return alreadySelectedCodes.length === searchResultsStore.results.length ? true : false;
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
const isSomeResultsSelected = computed(() => {
    if (selectedCodesStore.codes.length > 0) {
        const alreadySelectedCodes = searchResultsStore.results.filter((resultItem) => {
            return selectedCodesStore.codes.some((code) => resultItem.id === code.id);
        }); 
        return alreadySelectedCodes.length >= 1 && alreadySelectedCodes.length !== searchResultsStore.results.length ? true : false;
    } else {
        return false;
    }
});

/**
 * To determine the check display of the checkbox
 *
 * @returns { Boolean } 
 */
const isChecked = computed(() => isAllResultsSelected.value ? true : false);

/**
 * dispatch to parent so can move to relevant settings page
 */
function onSelectAll () {
    isChecked.value ? removeAllResults() : addAllResults();
}

/**
 * pull all results out 
 */
function removeAllResults () {
    searchResultsStore.removeAllResults();
}

/**
 * add all results in 
 */
function addAllResults () {
    searchResultsStore.addAllResults();
}

</script>

<template>
    <button class="search-result"
        @click.prevent="onSelectAll"
        @keyup.enter="onSelectAll"
    >
        <div class="checklist">
            <input 
                id="checkbox-select-all"
                type="checkbox"
                :checked="isChecked"
                :class="{ 'indeterminate': isSomeResultsSelected }"
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

input[type=checkbox]:not(.plain) + label {
    margin: 0;
}

</style>

<script setup>

/**
 * 
 */

import { computed } from 'vue';
import { useSearchResultsStore } from './store/csSearchResultsStore'; 
import { useSelectedCodesStore } from './store/csSelectedCodesStore'; 


const searchResultsStore = useSearchResultsStore();
const selectedCodesStore = useSelectedCodesStore();

/**
 * To determine the behaviour of the 'Select all classes' button
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
 * To add the 'indeterminate' class to the select all checkbox
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
const isChecked = computed(() => {
    return isAllResultsSelected.value ? true : false;
});

/**
 * dispatch to parent so can move to relevant settings page
 */
function onSelectAll () {
    if (isChecked.value) {
        removeAllResults();
    } else {
        addAllResults();
    }
}

/**
 * pull all results out 
 */
function removeAllResults () {
    const matchedSelectedCodes = searchResultsStore.results.filter((matchedSelectedCode) => { //check if the code is in the list already
        return selectedCodesStore.codes.some((selectedClass) => selectedClass.id === matchedSelectedCode.id); //add in those that match
    });
    selectedCodesStore.remove(matchedSelectedCodes);
    // matchedSelectedCodes.forEach((matchedSelectedCode) => matchedSelectedCode.isSelected = false); //set false on all
    // selectedClasses.value = selectedClasses.value.filter((selectedClass) => selectedClass.isSelected); //and then remove those there were just unset  
}

/**
 * add all results in 
 */
function addAllResults () {
    const newSelectedCodes = searchResultsStore.results.filter((newSelectedCode) => { //check if the code isn't in the list already
        return !selectedCodesStore.codes.some((selectedClass) => selectedClass.id === newSelectedCode.id); //don't add in those that match
    });
    selectedCodesStore.add(newSelectedCodes);
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

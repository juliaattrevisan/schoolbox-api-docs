<script setup>

/**
 * 
 */

import { computed } from 'vue';
import { escapeRegExp } from '@js/util/jsUtils';
import { useSearchResultsStore } from './store/csSearchResultsStore'; 
import { useSelectedCodesStore } from './store/csSelectedCodesStore'; 


const searchResultsStore = useSearchResultsStore();
const selectedCodesStore = useSelectedCodesStore();


const props = defineProps({
    result: {
        type: Object,
        required: true,
        default: () => {
            return {
                id: 0,
                name: 'name',
            }
        }
    },
});

const highlightTitleSearch = computed(() => {
    return highlightSearchInElement(props.result.name);
});

/**
 * To highlight where the search string matches within the settings result
 * (?![^>]*>) is used to check we are not inside an element node 
 *
 * @params { String } the string to find all instanced of the current search string in
 *
 * @returns { String } string for v-html with <mark></mark> wrapped around each found result
 */
function highlightSearchInElement (toHighlight) {
    let highlighted = toHighlight;
    searchResultsStore.queryWordList.forEach((wordInSearch) => {
        highlighted = highlighted.replace(RegExp(`${escapeRegExp(wordInSearch)}(?![^>]*>)`, 'ig'), (match) => {
            return `<mark>${match}</mark>`;
        });
    });
    return highlighted;
}

/**
 * To determine whether the checkbox is currently selected 
 * based on whether it's in the selected list
 *
 * @returns { Boolean }
 */
const isCodeSelected = computed(() => { 
    return selectedCodesStore.codes.some((code) => props.result.id === code.id);
});

/**
 * To determine the display of the checkbox based on whether
 * the code is currently selected
 */
function onSelected () {
    if (isCodeSelected.value) {
        selectedCodesStore.remove(props.result);
    } else {
        selectedCodesStore.add(props.result);
    }
}

</script>

<template>
    <button 
        class="search-result"
        @click.prevent="onSelected"
        @keyup.enter="onSelected"
    >
        <div class="checklist">
            <input 
                :id="`checkbox-${result.id}`"
                type="checkbox"
                :checked="isCodeSelected"
            />
            <label :for="`checkbox-${result.id}`"></label>
        </div>
        <span v-html="highlightTitleSearch"></span>
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

input[type=checkbox]:not(.plain) + label {
    margin: 0;
}

</style>

<script setup>

/**
 * An individual class search result that matches the search query with search result
 * highlighted within. Selecting will add the class to the list
 * Also included is a 'Select All' feature which toggles the selection of all
 * results currently shown
 */

import { computed } from 'vue';
import { escapeRegExp } from '@js/util/jsUtils';


const emit = defineEmits(['select']);

const props = defineProps({
    result: {
        type: Object,
        required: true,
        default: () => {
            return {
                id: 0,
                name: 'name',
                isSelected: false,
            }
        }
    },
    isAllResultsSelected: { //to determine the behaviour of the 'Select all classes' button
        type: Boolean,
        required: true,
        default: false,
    },
    isSomeResultsSelected: { //to add the 'indeterminate' class to the select all checkbox
        type: Boolean,
        required: true,
        default: false,
    },
    queryWordList: { //current word list in the query
        type: Array,
        required: true,
        default: [],
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
    props.queryWordList.forEach((wordInSearch) => {
        highlighted = highlighted.replace(RegExp(`${escapeRegExp(wordInSearch)}(?![^>]*>)`, 'ig'), (match) => {
            return `<mark>${match}</mark>`;
        });
    });
    return highlighted;
}

/**
 * To determine the check display of the checkbox
 *
 * @returns { Boolean } 
 */
const isChecked = computed(() => {
    return props.result.isSelected;
});

/**
 * dispatch to parent so can move to relevant settings page
 */
function onSelect () {
    emit('select', props.result);
}

</script>

<template>
    <button class="search-result"
        @click.prevent="onSelect"
        @keyup.enter="onSelect"
    >
        <div class="checklist">
            <input 
                :id="`checkbox-${result.id}`"
                type="checkbox"
                :checked="isChecked"
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

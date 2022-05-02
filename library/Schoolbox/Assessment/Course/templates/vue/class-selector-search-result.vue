<script setup>

/**
 * An individual class search result that matches the search query with search result
 * highlighted within. Selecting will add the class to the list
 * Also included is a 'Select All' feature which toggles the selection of all
 * results currently shown
 */

import { ref, computed } from 'vue';
import { escapeRegExp } from '@js/util/jsUtils';


const emit = defineEmits(['mouse-over-result', 'toggle', 'toggle-all']);

const props = defineProps({
    result: {
        type: Object,
        required: true,
        default: () => {
            return {
                id: 0,
                name: 'name',
                isSelectAll: false,
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

const isActive = ref(false);
const resultEl = ref(null);

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
 * To determine the check dispaly of the checkbox
 *
 * @returns { Boolean } 
 */
const isChecked = computed(() => {
    if (!props.result.isSelectAll) {
        return props.result.isSelected;
    } else if (props.isAllResultsSelected) {
        return true;
    } else {
        return false;
    }
});

/**
 * so mouse selection takes precendence over the arrow/enter selection
 */
function mouseOverResult () {
    emit('mouse-over-result');
}

/**
 * dispatch to parent so can move to relevant settings page
 */
function selectResult () {
    if (props.result.isSelectAll) {
        emit('toggle-all');
    } else {
        emit('toggle', props.result);
    }
}

</script>

<template>
    <button class="search-result"
        ref="resultEl"
        :class="{ 'search-result-active': isActive }"
        @mouseover="mouseOverResult"
        @focus="mouseOverResult"
        @click.prevent="selectResult"
        @keyup.enter="selectResult"
    >
        <div class="checklist">
            <input 
                :id="`checkbox-${result.id}`"
                type="checkbox"
                :checked="isChecked"
                :class="{ 'indeterminate': result.isSelectAll && isSomeResultsSelected }"
            />
            <label :for="`checkbox-${result.id}`"></label>
        </div>
        <strong v-if="result.isSelectAll">Select All</strong>
        <span v-else v-html="highlightTitleSearch"></span>
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

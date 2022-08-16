<script setup>

/**
 * A single search result that be selected / unselected with a checkbox status
 */

import { computed } from 'vue';
import { escapeRegExp } from '@js/util/jsUtils';
import { useSearchStore  } from './store/mgSearchStore';


const searchStore = useSearchStore();


const props = defineProps({
    result: {
        type: Object,
        required: true,
        default: () => {
            return {
                id: 0,
                name: '',
            }
        }
    },
});

/**
 * To highlight the search selections in the name
 *
 * @returns { HTML }
 */
const highlightNameSearch = computed(() => highlightSearchInElement(props.result.name));


/**
 * To highlight where the search string matches within the settings result
 * (?![^mark>]*mark>) is used to check we are not inside an element node 
 *
 * @params { String } the string to find all instanced of the current search string in
 *
 * @returns { String } string for v-html with <mark></mark> wrapped around each found result
 */
function highlightSearchInElement (toHighlight) {
    let highlighted = toHighlight;
    highlighted = highlighted.replace(RegExp(`${escapeRegExp(searchStore.query)}(?![^mark>]*mark>)`, 'ig'), (match) => {
        return `<mark>${match}</mark>`;
    });
    return highlighted;
}

/**
 * To determine whether the checkbox is currently selected 
 * based on whether it's in the selected list
 *
 * @returns { Boolean }
 */
const isSelected = computed(() => { 
    return searchStore.selectedResults.some((selectedItem) => props.result.id === selectedItem.id);
});

/**
 * To determine the display of the checkbox based on whether
 * the result is currently selected
 */
function onSelected () {
    isSelected.value ? searchStore.removeResult(props.result) : searchStore.addResult(props.result);
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
                :checked="isSelected"
            />
            <label :for="`checkbox-${result.id}`"></label>
        </div>
        <div>
            <span v-html="highlightNameSearch"></span>
        </div>
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
}

input[type=checkbox]:not(.plain) + label {
    margin: 0;
}

aside {
    font-size: 0.725rem;
    font-style: italic;
}

</style>

<script setup>

/**
 * To search for members to add
 */

import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import debounce from 'lodash/debounce';
import { useAddMembersStore } from './store/mgAddMembersStore';
import { useSearchStore } from './store/mgSearchStore';
import FromTypes from './mgAddMembersFromTypes';
import MgSearchResults from './mg-search-results.vue';


const addMembersStore = useAddMembersStore();
const searchStore = useSearchStore();
const searchEl = ref(null);

/**
 * Update the input place holder text depending on the currentFromSelect
 *
 * @returns { String }
 */
const inputPlaceholder = computed(() => {
    const placeholderOptions = {
        [FromTypes.NAME]: 'Search users...',
        [FromTypes.GROUP]: 'Search groups...',
        [FromTypes.ROLE]: 'Search roles...',
        [FromTypes.LIST]: 'Add from a list...',
    }
    return placeholderOptions[addMembersStore.fromType];
});

/**
 * add relevant keyboard and mouse events
 */
onMounted(() => {
    document.addEventListener('keyup', onKeyUp);
    document.addEventListener('click', onMouseClick);
});

/**
 * remove relevant keyboard and mouse events
 */
onUnmounted(() => {
    document.removeEventListener('keyup', onKeyUp);
    document.removeEventListener('click', onMouseClick);
});

/**
 * so we can select enter when adding codes
 * useful if we copy/paste a bunch of codes,
 * then we can just select enter to add them all
 * 
 * @param { Event }
 */
function onKeyUp (event) {
    const keyName = event.key;
    const keyOptions = {
        'Escape': () => onEscapeKeySelected(),
    }
    if (keyOptions[keyName]) { //ensure search input has focus
    // if (keyOptions[keyName] && document.activeElement === queryInputEl.value) { //ensure search input has focus
        event.preventDefault();
        keyOptions[keyName](event);
    }
}

/**
 * to close the results when selected
 * 
 * @param { Event }
 */
function onEscapeKeySelected (event) {
    if (searchStore.isSearchActive) {
        searchStore.clear();
    }
}

/**
 * to be able to close the search results
 * when selection outside is made
 * 
 * @param { Event }
 */
function onMouseClick (event) {
    if (searchStore.isSearchActive && searchEl.value && !searchEl.value.contains(event.target)) {
        searchStore.clear();
    }
}

/**
 * start searching once the search is active, watching the query string for input
 */
watch(() => searchStore.query, () => {
    if (searchStore.isSearchActive) {
        debounceSearch();
    } 
});

/**
 * debounce to avoid constant polling while the user is typing
 */
const debounceSearch = debounce(() => {
    searchStore.search();
}, 350);

</script>

<template>
   <div ref="searchEl">
       <input
            type="text"
            v-model="searchStore.query"
            :placeholder="inputPlaceholder"
            @input="$event.target.composing = false"
        />
        <mg-search-results />
   </div>
</template>

<style lang="scss" scoped>

input {
    margin-bottom: 0; //so search-results can butt right up
}

</style>
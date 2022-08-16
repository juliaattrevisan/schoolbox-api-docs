<script setup>

/**
 * To search for members to add
 */

import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import debounce from 'lodash/debounce';
import { useSearchStore } from './store/mgSearchStore';
import MgSearchResults from './mg-search-results.vue';


const searchStore = useSearchStore();
const currentFromSelect = ref('name');
const searchEl = ref(null);

/**
 * Update the input place holder text depending on the currentFromSelect
 *
 * @returns { String }
 */
const inputPlaceholder = computed(() => {
    const placeholderOptions = {
        'name': 'Search users...',
        'group': 'Search groups...',
        'role': 'Search roles...',
        'list': 'Add from a list...',
    }
    return placeholderOptions[currentFromSelect.value];
});

/**
 * add relevant keyboard and mouse events
 */
onMounted(() => {
    // document.addEventListener('keydown', onKeyDown);
    // document.addEventListener('keyup', onKeyUp);
    document.addEventListener('click', onMouseClick);
});

/**
 * remove relevant keyboard and mouse events
 */
onUnmounted(() => {
    // document.removeEventListener('keydown', onKeyDown);
    // document.removeEventListener('keyup', onKeyUp);
    document.removeEventListener('click', onMouseClick);
});

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
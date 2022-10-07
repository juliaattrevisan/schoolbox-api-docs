<script setup>

/**
 * To search the currently selected curriculum node
 */

import { computed, watch } from 'vue';
import debounce from 'lodash/debounce';
import { useModalActiveNodesStore } from '../store/csModalActiveNodesStore';
import { useModalSearchStore } from '../store/csModalSearchStore';


const modalActiveNodesStore = useModalActiveNodesStore();
const modalSearchStore = useModalSearchStore();

/**
 * Only enable search once a first node is selected
 *
 * @returns { Boolean }
 */
const isDisabled = computed(() => !modalActiveNodesStore.isActive);

/**
 * Only enable search once a first node is selected
 *
 * @returns { String }
 */
const placeholder = computed(() => {
    if (isDisabled.value) {
        return 'Expand a curriculum to enable search...'
    } else {
        return `Search for codes or keywords...`;
    }
});

/**
 * start searching once the search is active, watching the query string for input
 */
 watch(() => modalSearchStore.query, () => {
    if (modalSearchStore.isActive) {
        debounceSearch();
    }
});

/**
 * debounce to avoid constant polling while the user is typing
 */
const debounceSearch = debounce(() => {
    modalSearchStore.search();
}, 350);

</script>

<template>
   <input
        v-model="modalSearchStore.query"
        ref="queryInputEl"
        type="text"
        class="search-input"
        :disabled="isDisabled"
        :placeholder="placeholder"
        data-modal-search-input
        @input="$event.target.composing = false"
    />
</template>

<style scoped>


</style>
<script setup>

/**
 * The modal header, containing the title, select nodes and search input
 */

import { computed, watch } from 'vue';
import debounce from 'lodash/debounce';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';
import { useSearchStore, DEBOUNCE_AMOUNT } from '../store/mcSearchStore';


const props = defineProps({
    /**
     * to disable certain events when editing is taking place
     */
     isDisabled: {
        type: Boolean,
        required: true,
    },
});

const viewNodesStore = useViewNodesStore();
const searchStore = useSearchStore();

/**
 * Only enable search once a first node is selected
 *
 * @returns { Boolean }
 */
const isDisabled = computed(() => !viewNodesStore.isActive || props.isDisabled);

/**
 * Only enable search once a first node is selected
 *
 * @returns { String }
 */
const placeholder = computed(() => {
    if (!viewNodesStore.isActive) {
        return 'Select a Curricula to enable search...'
    } else {
        return `Search ${viewNodesStore.current.name} for codes or keywords...`;
    }
});

/**
 * start searching once the search is active, watching the query string for input
 */
watch(() => searchStore.query, () => {
    if (searchStore.isActive) {
        searchStore.enableSearch();
        debounceSearch();
    }
});

/**
 * debounce to avoid constant polling while the user is typing
 */
const debounceSearch = debounce(() => {
    searchStore.search();
}, DEBOUNCE_AMOUNT);

</script>

<template>
    <input
        v-model="searchStore.query"
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
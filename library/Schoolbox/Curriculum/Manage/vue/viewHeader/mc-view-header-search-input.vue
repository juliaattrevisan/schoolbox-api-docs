<script setup>

/**
 * To search the currently selected curriculum node
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
        return 'Expand a curriculum to enable search...'
    } else {
        return `Search for codes or keywords...`;
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

/**
 * Clear the current search query
 */
function onClearSearchSelected () {
    searchStore.clear();
}

</script>

<template>
    <div class="input-group">
        <input
            v-model="searchStore.query"
            ref="queryInputEl"
            type="text"
            :disabled="isDisabled"
            :placeholder="placeholder"
            data-modal-search-input
            @input="$event.target.composing = false"
        />
        <button
            v-if="searchStore.query !== ''"
            title="Clear the current search"
            class="button icon-close"
            @click.prevent="onClearSearchSelected"
            @keyup.enter="onClearSearchSelected"
        >
        Clear
        </button>
    </div>
</template>

<style scoped>


</style>
<script setup>
/**
 * To search the currently selected curriculum node
 */

import { computed, watch } from "vue";
import debounce from "lodash/debounce";
import { useModalSearchStore, DEBOUNCE_AMOUNT } from "../store/csModalSearchStore";
import { useModalViewNodesStore } from "../store/csModalViewNodesStore";

const props = defineProps({
    /**
     * to disable certain events when editing is taking place
     */
    isDisabled: {
        type: Boolean,
        required: true,
    },
});

const modalSearchStore = useModalSearchStore();
const modalViewNodesStore = useModalViewNodesStore();

/**
 * Only enable search once a first node is selected
 *
 * @returns { Boolean }
 */
const isDisabled = computed(() => !modalViewNodesStore.isActive || props.isDisabled);

/**
 * Only enable search once a first node is selected
 *
 * @returns { String }
 */
const placeholder = computed(() => {
    if (!modalViewNodesStore.isActive) {
        return "Expand a curriculum to enable search...";
    } else {
        return `Search for codes or keywords...`;
    }
});

/**
 * start searching once the search is active, watching the query string for input
 */
watch(
    () => modalSearchStore.query,
    () => {
        if (modalSearchStore.isActive) {
            modalSearchStore.enableSearch();
            debounceSearch();
        }
    }
);

/**
 * debounce to avoid constant polling while the user is typing
 */
const debounceSearch = debounce(() => {
    modalSearchStore.search();
}, DEBOUNCE_AMOUNT);

/**
 * Clear the current search query
 */
function onClearSearchSelected() {
    modalSearchStore.clear();
}
</script>

<template>
    <div class="input-group">
        <input
            v-model="modalSearchStore.query"
            ref="queryInputEl"
            type="text"
            class="search-input"
            :disabled="isDisabled"
            :placeholder="placeholder"
            @input="$event.target.composing = false"
        />
        <button
            v-if="modalSearchStore.query !== ''"
            title="Clear the current search"
            class="button icon-close"
            :disabled="isDisabled"
            @click.prevent="onClearSearchSelected"
        >
            Clear
        </button>
    </div>
</template>

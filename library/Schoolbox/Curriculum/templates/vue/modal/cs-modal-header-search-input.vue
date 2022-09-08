<script setup>

/**
 * The modal header, containing the title, select nodes and search input
 */

import { computed, watch } from 'vue';
import debounce from 'lodash/debounce';
import { useModalActiveCodesStore } from '../store/csModalActiveCodesStore';
import { useModalSearchStore } from '../store/csModalSearchStore';


const modalActiveCodesStore = useModalActiveCodesStore();
const modalSearchStore = useModalSearchStore();

/**
 * Only enable search once a first node is selected
 *
 * @returns { Boolean }
 */
const isDisabled = computed(() => !modalActiveCodesStore.isActive);

/**
 * Only enable search once a first node is selected
 *
 * @returns { String }
 */
const placeholder = computed(() => {
    if (isDisabled.value) {
        return 'Select a Curriculum Code to enable search...'
    } else {
        return `Search ${modalActiveCodesStore.current.name} for codes or keywords...`;
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
        @input="$event.target.composing = false"
    />
</template>

<style scoped>


</style>
<script setup>

/**
 * To search the currently active curriculum node
 */

import { computed } from 'vue';
import { useViewNodesStore } from './store/mcViewNodesStore';


const viewNodesStore = useViewNodesStore();

/**
 * Only enable search once a first node is selected
 *
 * @returns { Boolean }
 */
const isDisabled = computed(() => !viewNodesStore.isActive);

/**
 * Only enable search once a first node is selected
 *
 * @returns { String }
 */
const placeholder = computed(() => {
    if (isDisabled.value) {
        return 'Select a Curriculum Code to enable search...'
    } else {
        return `Search ${viewNodesStore.current.name} for codes or keywords...`;
    }
});

</script>

<template>
   <input
        v-model="viewNodesStore.query"
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
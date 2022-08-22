<script setup>

/**
 * To search the currently active curriculum node
 */

import { computed } from 'vue';
import { useVisibleNodesStore } from './store/mcVisibleNodesStore';


const visibleNodesStore = useVisibleNodesStore();

/**
 * Only enable search once a first node is selected
 *
 * @returns { Boolean }
 */
const isDisabled = computed(() => !visibleNodesStore.isActive);

/**
 * Only enable search once a first node is selected
 *
 * @returns { String }
 */
const placeholder = computed(() => {
    if (isDisabled.value) {
        return 'Select a Curriculum Code to enable search...'
    } else {
        return `Search ${visibleNodesStore.current.name} for codes or keywords...`;
    }
});

</script>

<template>
   <input
        v-model="visibleNodesStore.query"
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
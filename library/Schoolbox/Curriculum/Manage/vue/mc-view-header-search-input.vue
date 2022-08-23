<script setup>

/**
 * To search the currently active curriculum node
 */

import { computed } from 'vue';
import { useViewNodesStore } from './store/mcViewNodesStore';
import { useActiveNodeStore } from './activeNode/store/mcActiveNodeStore';


const viewNodesStore = useViewNodesStore();
const activeNodeStore = useActiveNodeStore();

/**
 * Only enable search once a first node is selected
 *
 * @returns { Boolean }
 */
// const isDisabled = computed(() => !viewNodesStore.isActive || activeNodeStore.isActive);
const isDisabled = computed(() => !viewNodesStore.isActive);

/**
 * Only enable search once a first node is selected
 *
 * @returns { String }
 */
const placeholder = computed(() => {
    if (viewNodesStore.isActive) {
        return `Search ${viewNodesStore.current.name} for codes or keywords...`;
    } else {
        return 'Select a Curriculum Code to enable search...'  ;
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
<script setup>

/**
 * To search the currently active curriculum node
 */

import { computed } from 'vue';
import { useActiveNodesStore } from './store/mcActiveNodesStore';


const activeNodesStore = useActiveNodesStore();

/**
 * Only enable search once a first node is selected
 *
 * @returns { Boolean }
 */
const isDisabled = computed(() => !activeNodesStore.isActive);

/**
 * Only enable search once a first node is selected
 *
 * @returns { String }
 */
const placeholder = computed(() => {
    if (isDisabled.value) {
        return 'Select a Curriculum Code to enable search...'
    } else {
        return `Search ${activeNodesStore.current.name} for codes or keywords...`;
    }
});

</script>

<template>
   <input
        v-model="activeNodesStore.query"
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
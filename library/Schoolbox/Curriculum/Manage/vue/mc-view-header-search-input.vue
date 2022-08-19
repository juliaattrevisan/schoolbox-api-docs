<script setup>

/**
 * To search the currently active curriculum node
 */

import { computed } from 'vue';
import { useActiveCodesStore } from './store/mcActiveCodesStore';


const activeCodesStore = useActiveCodesStore();

/**
 * Only enable search once a first node is selected
 *
 * @returns { Boolean }
 */
const isDisabled = computed(() => !activeCodesStore.hasSelectedFirstNode);

/**
 * Only enable search once a first node is selected
 *
 * @returns { String }
 */
const placeholder = computed(() => {
    if (isDisabled.value) {
        return 'Select a Curriculum Code to enable search...'
    } else {
        return `Search ${activeCodesStore.activeNode.name} for codes or keywords...`;
    }
});

</script>

<template>
   <input
        v-model="activeCodesStore.query"
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
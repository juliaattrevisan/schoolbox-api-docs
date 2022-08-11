<script setup>

/**
 * The modal header, containing the title, select nodes and search input
 */

import { computed } from 'vue';
import { useModalActiveCodesStore } from '../store/csModalActiveCodesStore';


const modalActiveCodesStore = useModalActiveCodesStore();

/**
 * Only enable search once a first node is selected
 *
 * @returns { Boolean }
 */
const isDisabled = computed(() => !modalActiveCodesStore.hasSelectedFirstNode);

/**
 * Only enable search once a first node is selected
 *
 * @returns { String }
 */
const placeholder = computed(() => {
    if (isDisabled.value) {
        return 'Select a Curriculum Code to enable search...'
    } else {
        return `Search ${modalActiveCodesStore.selectedCode.name} for codes or keywords...`;
    }
});

</script>

<template>
   <input
        v-model="modalActiveCodesStore.query"
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
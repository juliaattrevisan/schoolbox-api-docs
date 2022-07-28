<script setup>

/**
 * The Curriuclum codes title and clear all button
 */

import { useSelectedCodesStore } from './store/csSelectedCodesStore';


const selectedCodesStore = useSelectedCodesStore();


/**
 * Clear all the currently selected codes
 */
function onClearAllSelected () {
    selectedCodesStore.empty();
}

</script>

<template>
   <div class="heading">
        <label>Curriculum Codes
            <span data-tooltip="" class="hint has-tip tip-top" data-width="200" data-selector="tooltip-l5qfe53bv" aria-describedby="tooltip-l5qfe53bv" title="">?</span>
        </label>
        <button 
            v-if="selectedCodesStore.hasCodes"
            class="clear-all-btn" 
            @click.prevent="onClearAllSelected" 
            @keyup.enter="onClearAllSelected" 
            title="Clear All Curriculum Codes"
        >
            Clear All
        </button>
    </div>
</template>

<style lang="scss" scoped>

.heading {
    display: flex;
    gap: 1rem;
}

.clear-all-btn {
    background: none;
    font-size: 0.725rem;
    margin: 0;
    min-height: auto;
    padding: 0rem;
}

</style>
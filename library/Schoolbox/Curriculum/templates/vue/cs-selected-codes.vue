<script setup>

/**
 * To list all the currently selected Curriculum codes
 */

import { useSelectedCodesStore } from './store/csSelectedCodesStore';
import CsSelectedCode from './cs-selected-code.vue';


const selectedCodesStore = useSelectedCodesStore();

</script>

<template>
    <div v-if="selectedCodesStore.hasCodes" class="selected-codes-container">
        <cs-selected-code
            v-for="code in selectedCodesStore.codes"
            :key="code.id"
            :code="code"
        >
        </cs-selected-code>
    </div>
</template>

<style lang="scss" scoped>

.selected-codes-container {
    border: 1px solid var(--form-border-color);
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: -1px; //to butt up against the search input
    min-height: 50px;
    padding: 0.5rem;
}

    .selected-codes-container__disabled {
        opacity: 0.7;
    }

</style>
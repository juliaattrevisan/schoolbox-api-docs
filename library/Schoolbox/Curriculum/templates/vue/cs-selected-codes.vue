<script setup>

/**
 * To list all the currently selected Curriculum codes
 */

import { computed } from 'vue';
import { useStore } from './store/curriculumSelectorStore';
import { useSelectedCodesStore } from './store/csSelectedCodesStore';
import CsSelectedCode from './cs-selected-code.vue';


const store = useStore();
const selectedCodesStore = useSelectedCodesStore();

/**
 * Add a comma separated string of the ids of the selected codes 
 * for use in forms
 *
 * @returns { String }
 */
const curriculumCodes = computed(() => {
    const justIds = selectedCodesStore.codes.map((code) => code.id);
    return justIds.join(',');
});

</script>

<template>
    <div v-if="selectedCodesStore.hasCodes" class="selected-codes-container" data-test-container>
        <cs-selected-code
            v-for="code in selectedCodesStore.codes"
            :key="code.id"
            :code="code"
            data-test-code
        />
    </div>
    <div v-else-if="selectedCodesStore.hasNoCodes && store.isReadOnly" class="selected-codes-container" data-test-container>
        <aside class="meta no-codes-note">No Curriculum Codes have been selected</aside>
    </div>
    <input id="curriculumCodes[]" name="curriculumCodes[]" type="hidden" tabindex="-1" :value="curriculumCodes" />
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

.no-codes-note {
    margin-top: 0.35rem;
}

</style>
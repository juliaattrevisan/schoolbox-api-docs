<script setup>

/**
 * To list all the currently selected Curriculum codes
 */

import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { useStore } from './store/curriculumSelectorStore';
import { useSelectedCodesStore } from './store/csSelectedCodesStore';
import CsSelectedCode from './cs-selected-code.vue';


const store = useStore();
const selectedCodesStore = useSelectedCodesStore();
const valuesAndLabelsEl = ref(null);


/**
 * to listen for any clears of the selected codes from outside the Vue app
 */
onMounted(() => {
    document.addEventListener('CURRICULUM-SELECTOR__CLEAR_SELECTED_CODES', (event) => {
        selectedCodesStore.empty();
    });
});

/**
 * Add a comma separated string of the ids of the selected codes
 * for use in forms
 *
 * @returns { String }
 */
const ids = computed(() => {
    return selectedCodesStore.codesIds.join(',');
});

/**
 * This is currently exclusively for use in library/Schoolbox/Filter/templates/panel.twig
 * We decode this array on the other side to display the Curriculum codes within the selected filters panel
 *
 * @returns { String }
 */
const valuesAndLabels = computed(() => {
    const justIdsAndLabels = selectedCodesStore.codes.map((code) => {
        return { value: code.id, label: code.code }
    });
    return JSON.stringify(justIdsAndLabels);
});

/**
 * To manually dispatch a change event when there's been a change in the selected codes
 * We listen for this in library/Schoolbox/Filter/templates/panel.twig
 */
watch(() => selectedCodesStore.codesIds, () => {
    nextTick(() => { //nextTick to ensure the dom has updated the value property
        const event = new Event('change');
        valuesAndLabelsEl.value.dispatchEvent(event);
    });
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
    <input :id="store.formValueName" :name="store.formValueName" type="hidden" tabindex="-1" :value="ids" />
    <input ref="valuesAndLabelsEl" type="hidden" tabindex="-1" :value="valuesAndLabels" data-selected-curriculum-codes />
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

.no-codes-note {
    margin-top: 0.35rem;
}

</style>

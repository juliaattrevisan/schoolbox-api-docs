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
const codesInputEl = ref(null);
const selectEl = ref(null);


/**
 * to listen for any clears of the selected codes from outside the Vue app
 */
onMounted(() => {
    document.addEventListener('FILTERS__CLEAR_FILTER', (event) => {
        if (codesInput.value === event.detail.elementId) { //just in case there are multiple on the page
            selectedCodesStore.empty();
        }
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
 * We use to compare the curriculum selector that's just been cleared in the CLEAR_SELECTED_CODES event
 *
 * @returns { String }
 */
const codesInput = computed(() => {
    return `${store.formValueName}-selected-codes`;
});

/**
 * This is currently exclusively for use in library/Schoolbox/Filter/templates/panel.twig
 * We JSON.parse() this string on the other side to display the Curriculum codes within the selected filters panel
 *
 * @returns { String }
 */
const codes = computed(() => {
    const codes = selectedCodesStore.codes.map((code) => {
        return { id: parseInt(code.id), code: code.code }
    });
    return JSON.stringify(codes);
});

/**
 * To manually dispatch a change event when there's been a change in the selected codes
 * We listen for this in library/Schoolbox/Filter/templates/panel.twig
 */
watch(() => selectedCodesStore.codesIds, () => {
    nextTick(() => { //nextTick to ensure the dom has updated the value property
        const options = selectEl.value.querySelectorAll('option');
        options.forEach((option) => {
            option.setAttribute('selected', 'selected');
        });
        const event = new Event('change');
        selectEl.value.dispatchEvent(event);
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
    <!-- <input :id="codesInput" ref="codesInputEl" type="hidden" tabindex="-1" :value="codes" data-selected-curriculum-codes /> -->
    <select :id="`${store.formValueName}[]`" :name="`${store.formValueName}[]`" multiple tabindex="-1" ref="selectEl" style="display: none;">
        <option
            v-for="code in selectedCodesStore.codes"
            :key="code.id"
            :value="code.id"
        >
            {{ code.code }}
        </option>
    </select>

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

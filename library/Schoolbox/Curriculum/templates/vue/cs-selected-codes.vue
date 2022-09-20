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
const selectEl = ref(null);


/**
 * to listen for any clears of the selected codes from outside the Vue app
 */
onMounted(() => {
    document.addEventListener('SEARCH_SELECT__UPDATE_SELECTION', (event) => {
        if (selectEl.value.getAttribute('id') === event.detail.elementId) { //just in case there are multiple on the page
            const newSelection = JSON.parse(event.detail.selection);
            selectedCodesStore.update(newSelection);
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
 * To manually dispatch a <select> change event when there's been a change in the selected codes
 * We listen for this in library/Schoolbox/Filter/templates/panel.twig
 */
watch(() => selectedCodesStore.codesIds, () => {
    nextTick(() => { //nextTick to ensure the dom has updated so we can access with the latest changes
        const options = selectEl.value.querySelectorAll('option');
        /**
         * Vue currently removes the selected attribute from an <option> even though a v-model isn't linked
         * So we have to add them in manually rather than in the template
         * We add these in so within the filters library/Schoolbox/Filter/templates/panel.twig,
         * The selected values can be serialised using $optionInputs.serializeArray()
         */
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
    <select
        ref="selectEl"
        :id="`${store.formValueName}[]`"
        :name="`${store.formValueName}[]`"
        :data-selected-original="JSON.stringify(store.selectedOriginal)"
        :data-selected-default="JSON.stringify(store.selectedDefault)"
        tabindex="-1"
        multiple
        data-search-select
    >
        <option
            v-for="code in selectedCodesStore.codes"
            :key="code.id"
            :value="code.id"
            :data-selected-object="JSON.stringify(code)"
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

select[data-search-select] {
    display: none;
}

</style>

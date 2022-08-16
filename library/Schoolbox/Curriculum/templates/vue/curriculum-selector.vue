<script setup>

/**
 * To select Curriculum Codes to assign to something like a Due Work
 */

import { ref, onMounted } from 'vue';
import { useStore } from './store/curriculumSelectorStore';
import { useSelectedCodesStore } from './store/csSelectedCodesStore';
import { useSearchResultsStore } from './store/csSearchResultsStore';
import { useModalStore } from './store/csModalStore';
import { useModalActiveCodesStore } from './store/csModalActiveCodesStore';
import CsSearchInput from './cs-search-input.vue';
import CsSelectedCodes from './cs-selected-codes.vue';
import CsSearchResults from './cs-search-results.vue';
import CsModal from './modal/cs-modal.vue';
import CsActiveSelectedCodeModal from './cs-active-selected-code-modal.vue';


const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    dataEndpoint: {
        type: String,
        required: false,
        default: '',
    },
    /**
     * the codes already previously selected
     */
    selectedCodes: {
        type: Array,
        required: false,
        default: [],
    },
    /**
     * can only view the currently selected codes, not edit them
     */
    isReadOnly: {
        type: Boolean,
        required: false,
        default: true,
    },
});

const curriculumSelectorEl = ref(null);

/**
 * Start loading the curriculum data
 */
onMounted(() => {
    checkForStorybook();
    const store = useStore();
    store.isReadOnly = props.isReadOnly;
    if (!store.isReadOnly && props.dataEndpoint !== '') { //only load when we need to
        store.load(props.dataEndpoint);
    }
    if (props.selectedCodes.length > 0) { //add in any codes that have been selected previously
        useSelectedCodesStore().add(props.selectedCodes);
    }
});

/**
 * When switching between stories, we need to reset the data models
 * as they persist between the stories otherwise
 */
function checkForStorybook () {
    if (typeof STORYBOOK_ENV !== 'undefined') {
        useSelectedCodesStore().$reset();
        useSearchResultsStore().$reset();
        useModalStore().$reset();
        useModalActiveCodesStore().$reset();
        useStore().$reset();
    }
}

</script>

<template>
   <div ref="curriculumSelectorEl">
        <cs-selected-codes />
        <cs-search-input :curriculum-selector-el="curriculumSelectorEl" />
        <cs-search-results />
    </div>
    <cs-modal />
    <cs-active-selected-code-modal />
</template>

<style lang="scss" scoped>

.columns {
    margin-bottom: 1rem;
}

</style>

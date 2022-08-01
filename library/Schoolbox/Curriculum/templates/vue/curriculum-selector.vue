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
import CsHeading from './cs-heading.vue';
import CsSearchInput from './cs-search-input.vue';
import CsSelectedCodes from './cs-selected-codes.vue';
import CsSearchResults from './cs-search-results.vue';
import CsModal from './modal/cs-modal.vue';


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
        defalt: [],
    },
});

const curriculumSelectorEl = ref(null); 

/**
 * Start loading the curriculum data
 */
onMounted(() => {
    if (STORYBOOK_ENV) {
        resetForStorybook();
    }
    if (props.dataEndpoint !== '') {
        useStore().load(props.dataEndpoint);
    }
    if (props.selectedCodes.length > 0) {
        useSelectedCodesStore().add(props.selectedCodes);
    }
});

/**
 * When switching between stories, we need to reset the data models
 * as they persist between the stories otherwise
 */
function resetForStorybook () {
    useSelectedCodesStore().$reset();
    useSearchResultsStore().$reset();
    useModalStore().$reset();
    useModalActiveCodesStore().$reset();
    useStore().$reset(); 
}

</script>

<template>
   <div class="row" ref="curriculumSelectorEl">
        <div class="small-12 columns">
            <cs-heading />
            <cs-selected-codes />
            <cs-search-input :curriculum-selector-el="curriculumSelectorEl" />
            <cs-search-results />
        </div>
    </div>
    <cs-modal />
</template>

<style lang="scss" scoped>

.columns {
    margin-bottom: 1rem;  
}

</style>
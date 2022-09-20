<script setup>

/**
 * To select Curriculum Codes to assign to something like a Due Work
 */

import { ref, onMounted, nextTick } from 'vue';
import { ResizeObserver } from 'vue3-resize';
import 'vue3-resize/dist/vue3-resize.css';
import { useStore } from './store/curriculumSelectorStore';
import { useSelectedCodesStore } from './store/csSelectedCodesStore';
import { useSearchStore } from './store/csSearchStore';
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
    /**
     * input name, to allow custom form structure
     */
    formValueName: {
        type: String,
        required: false,
        default: 'curriculumCodes[]',
    },
    /**
     * an 'original' selection, saved by the user
     */
     selectedOriginal: {
        type: Array,
        required: false,
        default: [],
    },
    /**
     * the default selection relevant to the current user role
     */
     selectedDefault: {
        type: Array,
        required: false,
        default: [],
    },
});

const store = useStore();
const curriculumSelectorEl = ref(null);


/**
 * Start loading the curriculum data
 */
onMounted(() => {
    checkForStorybook();
    store.isReadOnly = props.isReadOnly;
    store.formValueName = props.formValueName;
    if (!store.isReadOnly && props.dataEndpoint !== '') { //only load when we need to
        store.load(props.dataEndpoint);
    }
    if (props.selectedCodes?.length > 0) { //add in any codes that have been selected previously
        useSelectedCodesStore().add(props.selectedCodes);
    }
    checkContainerWidth();
});

/**
 * When switching between stories, we need to reset the data models
 * as they persist between the stories otherwise
 */
function checkForStorybook () {
    if (typeof STORYBOOK_ENV !== 'undefined') {
        useSelectedCodesStore().$reset();
        useSearchStore().$reset();
        useModalStore().$reset();
        useModalActiveCodesStore().$reset();
        useStore().$reset();
    }
}

/**
 * so can respond to dimenion changes if required
 */
async function checkContainerWidth () {
    await nextTick();
    store.updateContainerWidth(curriculumSelectorEl.value.offsetWidth);
}

/**
 * Any changes to the element size are tracked by ResizeObserver so can update dimensions
 */
function onContainerResize () {
    checkContainerWidth();
}

</script>

<template>
   <div ref="curriculumSelectorEl">
        <cs-selected-codes />
        <cs-search-input :curriculum-selector-el="curriculumSelectorEl" />
        <cs-search-results />
        <resize-observer @notify="onContainerResize" />
    </div>
    <cs-modal />
    <cs-active-selected-code-modal />
</template>

<style lang="scss" scoped>

</style>

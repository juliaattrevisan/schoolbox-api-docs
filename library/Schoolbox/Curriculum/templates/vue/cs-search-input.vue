<script setup>

/**
 * To search for codes or select the browse button to open the modal
 */

import { ref, onMounted } from 'vue'; 
import { useModalStore } from './store/csModalStore'; 
import { useSearchResultsStore } from './store/csSearchResultsStore'; 


const searchResultsStore = useSearchResultsStore();
const modalStore = useModalStore();
const isLoading = ref(false);
const hasLoadError = ref(false);
const isSelectorActive = ref(true);
const queryInputEl = ref(null); //to deterch if the search input is currently active
const inputPlaceholder = ref('Search for curriculum codes or keywords...');


const props = defineProps({
    /**
     * the parent DOM element, to detect mouse events
     */
    curriculumSelectorEl: {
        // type: Object,
        required: true,
    },
});

/**
 * enable relevant keyboard and mouse events
 */
onMounted(() => {
    initKeyboardEvents();
    initMouseEvents();
});

/**
 * to be able to select by enter and navigate by up/down arrows
 */
function initKeyboardEvents () {
    document.addEventListener('keydown', (event) => { //prevent the <form> from being submitted on enter
        if (event.key === 'Enter' && document.activeElement === queryInputEl.value) {
            event.preventDefault();
        }
    });
    document.addEventListener('keyup', (event) => {
        const keyName = event.key;
        const keyOptions = {
            'Enter': () => onEnterKeySelected(),
        }
        if (keyOptions[keyName] && document.activeElement === queryInputEl.value) { //ensure search input has focus
            event.preventDefault();
            keyOptions[keyName]();
        }
    });
}

/**
 * enter the manual entry or select the currently 
 * highlighted search result
 */
function onEnterKeySelected () {
    searchResultsStore.addAllResults();
    searchResultsStore.clear();
}

/**
 * to be able to close the search results
 * when selection outside is made
 */
function initMouseEvents () {
    document.addEventListener('click', (event) => {
        if (props.curriculumSelectorEl && !props.curriculumSelectorEl.contains(event.target)) {
            searchResultsStore.clear();
        }
    });
}

/**
 * to open the modal
 */
function onBrowseSelected () {
    modalStore.open();
}

</script>

<template>
    <div class="input-group">
        <input
            v-if="isSelectorActive"
            v-model="searchResultsStore.query"
            ref="queryInputEl"
            type="text"
            class="search-input"
            :readonly="isLoading || hasLoadError"
            :placeholder="inputPlaceholder"
            @input="$event.target.composing = false"
        />
        <button class="button icon-curriculum" title="Browse and select from the Curriculum" @click.prevent="onBrowseSelected">Browse</button>
        <input name="curriculumCodes[]" type="hidden" tabindex="-1" value="" />
    </div>
</template>

<style scoped>

input, 
button {
    margin-bottom: 0; 
}

</style>
<script setup>

/**
 * To search for codes or select the browse button to open the modal
 */

import { ref, computed, onMounted } from 'vue'; 
import { useStore } from './store/curriculumSelectorStore'; 
import { useModalStore } from './store/csModalStore'; 
import { useSearchResultsStore } from './store/csSearchResultsStore'; 


const store = useStore();
const modalStore = useModalStore();
const searchResultsStore = useSearchResultsStore();
const isSelectorActive = ref(true);
const queryInputEl = ref(null); //to deterch if the search input is currently active


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
 * notify of loading, a load error or that we are right to get searching
 * 
 * @returns { String } 
 */
const inputPlaceholder = computed(() => {
    if (store.isLoading) {
        return 'Loading Curriculum Codes...';
    } else if (store.hasLoadError) {
        return 'Currently unable to load Curriculum Codes. Please contact your IT support if the problem persists.';
    } else {
        return 'Search for curriculum codes or keywords...';
    }
    
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
    <div v-if="!store.isReadOnly" class="input-group">
        <input
            v-if="isSelectorActive"
            v-model="searchResultsStore.query"
            ref="queryInputEl"
            type="text"
            class="search-input"
            :readonly="!store.hasLoadedOk"
            :placeholder="inputPlaceholder"
            @input="$event.target.composing = false"
        />
        <button 
            v-if="store.hasLoadedOk" 
            class="button icon-curriculum" 
            title="Browse and select from the Curriculum" 
            @click.prevent="onBrowseSelected" 
            @keyup.enter="onBrowseSelected">
            Browse
        </button>
    </div>
</template>

<style scoped>

input, 
button {
    margin-bottom: 0; 
}

</style>
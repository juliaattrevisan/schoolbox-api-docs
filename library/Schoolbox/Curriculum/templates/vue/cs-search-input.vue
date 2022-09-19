<script setup>

/**
 * To search for codes or select the browse button to open the modal
 */

import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import debounce from 'lodash/debounce';
import { useStore } from './store/curriculumSelectorStore';
import { useModalStore } from './store/csModalStore';
import { useSearchStore } from './store/csSearchStore';


const store = useStore();
const modalStore = useModalStore();
const searchStore = useSearchStore();
const queryInputEl = ref(null); //to deterch if the search input is currently active


const props = defineProps({
    /**
     * the parent DOM element, to detect mouse events
     */
    curriculumSelectorEl: {
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
 * add relevant keyboard and mouse events
 */
onMounted(() => {
    document.addEventListener('keydown', onKeyDown);
    document.addEventListener('keyup', onKeyUp);
    document.addEventListener('click', onMouseClick);
});

/**
 * remove relevant keyboard and mouse events
 */
onUnmounted(() => {
    document.removeEventListener('keydown', onKeyDown);
    document.removeEventListener('keyup', onKeyUp);
    document.removeEventListener('click', onMouseClick);
});

/**
 * prevent the <form> from being submitted on enter
 *
 * @param { Event }
 */
function onKeyDown (event) {
    if (event.key === 'Enter' && document.activeElement === queryInputEl.value) {
        event.preventDefault();
    }
}

/**
 * so we can select enter when adding codes
 * useful if we copy/paste a bunch of codes,
 * then we can just select enter to add them all
 *
 * @param { Event }
 */
function onKeyUp (event) {
    const keyName = event.key;
    const keyOptions = {
        'Enter': () => onEnterKeySelected(),
    }
    if (keyOptions[keyName] && document.activeElement === queryInputEl.value) { //ensure search input has focus
        event.preventDefault();
        keyOptions[keyName]();
    }
}

/**
 * enter the manual entry or select the currently
 * highlighted search result
 */
function onEnterKeySelected () {
    searchStore.addAllResults();
    searchStore.clear();
}

/**
 * to be able to close the search results
 * when selection outside is made
 *
 * @param { Event }
 */
function onMouseClick (event) {
    if (searchStore.isActive && props.curriculumSelectorEl && !props.curriculumSelectorEl.contains(event.target)) {
        searchStore.clear();
    }
}

/**
 * start searching once the search is active, watching the query string for input
 */
 watch(() => searchStore.query, () => {
    if (searchStore.isActive) {
        debounceSearch();
    }
});

/**
 * debounce to avoid constant polling while the user is typing
 */
const debounceSearch = debounce(() => {
    searchStore.search();
}, 350);

/**
 * to open the modal
 */
function onBrowseSelected () {
    modalStore.open();
}

</script>

<template>
    <div v-if="!store.isReadOnly" class="input-group" :data-is-small-container="store.isSmallContainer">
        <input
            v-model="searchStore.query"
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

.input-group[data-is-small-container="true"] {
    flex-direction: column; /*stack the input and button when the container is small */
}

</style>
<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To search for codes or select the browse button to open the modal
 */

import debounce from "lodash/debounce";
import { useStore } from "./store/curriculumSelectorStore";
import { useSelectedNodesStore } from "./store/csSelectedNodesStore";
import { useModalStore } from "./store/csModalStore";
import { useSearchStore, DEBOUNCE_AMOUNT } from "./store/csSearchStore";

const store = useStore();
const selectedNodesStore = useSelectedNodesStore();
const modalStore = useModalStore();
const searchStore = useSearchStore();
const queryInputEl = window.Vue.ref(null); // to detect if the search input is currently active

const props = defineProps({
    /**
     * the parent DOM element, to detect mouse events
     */
    curriculumSelectorEl: {
        required: true,
    },
    /**
     * so can only select a single node
     */
    isSingleSelect: {
        type: Boolean,
        required: false,
        default: false,
    },
});

/**
 * notify of loading, a load error or that we are right to get searching
 *
 * @returns { String }
 */
const inputPlaceholder = window.Vue.computed(() => {
    if (store.isLoading) {
        return "Loading Curriculum Codes...";
    } else if (store.hasLoadError) {
        return "Currently unable to load Curriculum Codes. Please contact your IT support if the problem persists.";
    } else {
        return "Search for curriculum codes or keywords...";
    }
});

/**
 * add relevant keyboard and mouse events
 */
window.Vue.onMounted(() => {
    document.addEventListener("keydown", onKeyDown);
    document.addEventListener("keyup", onKeyUp);
    document.addEventListener("click", onMouseClick);
});

/**
 * remove relevant keyboard and mouse events
 */
window.Vue.onUnmounted(() => {
    document.removeEventListener("keydown", onKeyDown);
    document.removeEventListener("keyup", onKeyUp);
    document.removeEventListener("click", onMouseClick);
});

/**
 * prevent the <form> from being submitted on enter
 *
 * @param { Event }
 */
function onKeyDown(event) {
    if (event.key === "Enter" && document.activeElement === queryInputEl.value) {
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
function onKeyUp(event) {
    const keyName = event.key;
    const keyOptions = {
        Enter: () => onEnterKeySelected(),
    };
    if (keyOptions[keyName] && document.activeElement === queryInputEl.value) {
        //ensure search input has focus
        event.preventDefault();
        keyOptions[keyName]();
    }
}

/**
 * enter the manual entry or select the currently
 * highlighted search result
 */
function onEnterKeySelected() {
    searchStore.addAllResults();
    searchStore.clear();
}

/**
 * to be able to close the search results
 * when selection outside is made
 *
 * @param { Event }
 */
function onMouseClick(event) {
    if (searchStore.isActive && props.curriculumSelectorEl && !props.curriculumSelectorEl.contains(event.target)) {
        searchStore.clear();
    }
}

/**
 * start searching once the search is active, watching the query string for input
 */
window.Vue.watch(
    () => searchStore.query,
    () => {
        if (searchStore.isActive) {
            searchStore.enableSearch();
            debounceSearch();
        }
    },
);

/**
 * debounce to avoid constant polling while the user is typing
 */
const debounceSearch = debounce(() => {
    searchStore.search();
}, DEBOUNCE_AMOUNT);

/**
 * to open the modal
 */
function onBrowseSelected() {
    modalStore.open();
}

/**
 * Only display when the component is not read only or if
 * single select is active and no selection has been made
 * @returns {boolean} - whether the search input is active
 */
const isActive = window.Vue.computed(() => {
    if (store.isReadOnly) {
        return false;
    }
    if (props.isSingleSelect && selectedNodesStore.hasNodes) {
        return false;
    }
    return true;
});
</script>

<template>
    <div v-if="isActive" class="input-group" :data-is-small-container="store.isSmallContainer">
        <input
            ref="queryInputEl"
            v-model="searchStore.query"
            type="text"
            class="search-input"
            :readonly="!store.hasLoadedOk"
            :placeholder="inputPlaceholder"
            data-search-select-search-input
            @input="searchStore.query = $event.target.value /* Fix Android events SA-12170 */"
        />
        <button
            v-if="store.hasLoadedOk"
            type="button"
            class="button icon-curriculum"
            title="Browse and select from the Curriculum"
            @click.prevent="onBrowseSelected"
        >
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

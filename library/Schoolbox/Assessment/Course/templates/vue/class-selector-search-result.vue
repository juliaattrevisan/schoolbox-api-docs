<script setup>

/**
 * An individual search result that matches the search query with search result
 * highlighted within. Selecting will take the user to relevant page/setting
 *
 */

import { ref, computed, watch, onMounted, onUpdated, onBeforeUnmount } from 'vue';
import { getIndex } from '@js/util/domUtils';
import { escapeRegExp } from '@js/util/jsUtils';


const emit = defineEmits(['on-mouse-over-result', 'on-selected-result']);

const props = defineProps({
    result: {
        type: Object,
        required: true,
        default: () => {
            return {
                name: 'name',
            }
        }
    },
    queryWordList: { //current word list in the query
        type: Array,
        required: true,
        default: []
    },
    activeIndex: { //to know whether this result is currently active
        type: Number,
        required: true,
        default: 0
    }
});

const itemIndex = ref(-1);
const isActive = ref(false);
const resultEl = ref(null);

const highlightTitleSearch = computed(() => {
    return highlightSearchInElement(props.result.name);
});
    
watch(() => props.activeIndex, (newValue, oldValue) => {
    checkIndex();
});
    
onMounted(() => {
    checkIndex();
});

onUpdated(() => {
    checkIndex();
});

onBeforeUnmount(() => {
    checkIndex();
});


/**
 * to highlight the current selection relative to key events
 * needs to be updated once the animations have completed
 */
function checkIndex() {
    itemIndex.value = getIndex(resultEl.value.parentElement);
    if (itemIndex.value === props.activeIndex) {
        isActive.value = true;
    } else {
        isActive.value = false;
    }
}

/**
 * To highlight where the search string matches within the settings result
 * (?![^>]*>) is used to check we are not inside an element node 
 *
 * @params { String } the string to find all instanced of the current search string in
 *
 * @returns { String } string for v-html with <mark></mark> wrapped around each found result
 */
function highlightSearchInElement(toHighlight) {
    let highlighted = toHighlight;
    props.queryWordList.forEach((wordInSearch) => {
        highlighted = highlighted.replace(RegExp(`${escapeRegExp(wordInSearch)}(?![^>]*>)`, 'ig'), (match) => {
            return `<mark>${match}</mark>`;
        });
    });
    return highlighted;
}

/**
 * so mouse selection takes precendence over the arrow/enter selection
 */
function mouseOverResult() {
    emit('on-mouse-over-result');
}

/**
 * dispatch to parent so can move to relevant settings page
 */
function selectResult() {
    emit('on-selected-result', props.result);
}



</script>

<template>
    <button class="search-result"
        ref="resultEl"
        :class="{ searchResultActive: isActive }"
        @mouseover="mouseOverResult"
        @focus="mouseOverResult"
        @click.prevent="selectResult"
        @keyup.enter="selectResult"
    >
        <span class="result-title" v-html="highlightTitleSearch"></span>
    </button>
</template>

<style lang="scss" scoped>

.search-result {
    background: white;
    display: block;
    font-size: 0.825rem;
    margin: 0;
    padding: 0.5rem 0.75rem;
    text-align: left;
    transition: background 0.2s;
    width: 100%;
    &:hover {
        background: var(--content-ui-hover);
    }
}

.searchResultActive {
    background: var(--content-ui-hover);
}

</style>

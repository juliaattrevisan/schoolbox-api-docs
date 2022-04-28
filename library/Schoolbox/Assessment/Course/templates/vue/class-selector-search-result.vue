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
const resultEl = ref(null);
function checkIndex() {
    itemIndex.value = getIndex(resultEl.value.parentElement);
    if (itemIndex.value === props.activeIndex.value) {
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
    // this.$emit('on-selected-result', this.result.url);
}



</script>

<template>
    <a class="search-result"
        ref="resultEl"
        :class="{ searchResultActive: isActive }"
        @mouseover="mouseOverResult"
        @focus="mouseOverResult"
        @click.prevent="selectResult"
        @keyup.enter="selectResult"
    >
        <h4 class="result-title" v-html="highlightTitleSearch"></h4>
    </a>
</template>

<style lang="scss" scoped>

@import 'www/static/css/foundation/utils/_variables.scss';

.search-result {
    display: block;
    padding: .75rem;
    background: white;
    transition: back ground 0.2s;
    &:hover {
        background: $content-ui-hover;
    }
    h4 {
        margin-bottom: .5rem;
    }
    p {
        color: #9A9A9A;
        font-size: 13px;
        font-style: italic;
        margin-bottom: 0;
    }
}

.searchResultActive {
    background: $content-ui-hover;
}

</style>

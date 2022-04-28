<script setup>

/**
 * To help users find admin settings faster (hopefully!)
 * Search field is used to filter the results, then the relevant
 * search result can be selected to move to the relevant page/setting
 *
 */

import { ref, computed, watch, onMounted  } from 'vue'
import ClassSelectorSearchResult from './class-selector-search-result';
import { gsap } from 'gsap/all';


const props = defineProps({
    endpoint: {
        type: String,
        required: true,
    }
});

const classes = ref([]); //all the classes

const selectedClasses = ref([]); //the classes currently selected
const selectedClassIds = computed(() => {
    return selectedClasses.value.map((classItem) => classItem.id);
});

const isLoading = ref(true);
const hasLoadError = ref(false);
const query = ref('');
const minQueryLength = 1;
const activeIndex = ref(0); //to track which index is selected
const queryInputEl = ref(null);
const classSelectorEl = ref(null);
const searchResultsEl = ref([]);


/**
 * to notify whether still loading the results or not
 * 
 * @returns { String } loading status
 */
const inputPlaceholder = computed(() => {
    if (isLoading.value) {
        return 'Loading classes, please wait...'
    } else {
        return 'Search for classes...';
    }
});

/**
 * the search string broken into words, to search for each word
 *
 * @returns { Array } the search string broken into single words
 */
const queryWordList = computed(() => {
    const allWords = query.value.match(/\b(\w+)\b/g);
    const onlyWordsAboveTwoLetters = allWords.filter(word => word.length > 1);
    return onlyWordsAboveTwoLetters;
});

/**
 * the settings relevant to the search input
 * 
 * @returns { Array } all relevant search results, once we've reached the minQueryLength
 */
const resultsList = computed(() => {
    return query.value.length < minQueryLength ? [] : classes.value.filter(searchForTitle);
});

/**
 * find all those results that match EACH search word
 * within the the title field
 *
 * @params { Object }
 *
 * @return { Boolean } true if found
 */
function searchForTitle (classItem) {
    let wordMatchCount = 0;
    queryWordList.value.forEach(wordInSearch => {
        if (classItem.name?.toLowerCase().includes(wordInSearch.toLowerCase())) {
            wordMatchCount++;
        }
    });
    return wordMatchCount > 0 ? true : false; //if a word matches
}

/**
 * reset the active index when the list changes as the index may become out of range
 */
watch (resultsList, async (newValue, oldValue) => {
    if ((newValue.length !== oldValue.length) && (activeIndex.value > 0)) {
        activeIndex.value = 0;
    }
});


onMounted(() => {
    initKeyboardEvents();
    initMouseEvents();
    loadClassList();
});

/**
 * to be able to select by enter and navigate by up/down arrows
 */
function initKeyboardEvents () {
    document.addEventListener('keyup', (event) => {
        const keyName = event.key;
        const keyOptions = {
            'Enter': () => selectResult(resultsList.value[activeIndex.value].name),
            'ArrowUp': () => decrementActiveIndex(),
            'ArrowDown': () => incrementActiveIndex(),
        }
        if (keyOptions[keyName] && document.activeElement === queryInputEl.value) { //ensure search input has focus
            keyOptions[keyName]();
        }
    });
}

/**
 * to be able to move up and down through the selections
 * with the arrow keys
 */
function decrementActiveIndex () {
    if (activeIndex.value > 0) {
        activeIndex.value--;
    }
}

/**
 * to be able to move up and down through the selections
 * with the arrow keys
 */
function incrementActiveIndex () {
    if (activeIndex.value < (resultsList.value.length-1)) {
        activeIndex.value++;
    }
}

/**
 * to be able to close the menu when something else
 * is selected outside the menu
 */
function initMouseEvents () {
    document.addEventListener('click', (event) => {
        if (!classSelectorEl.value.contains(event.target)) {
            clearInput();
        }
    });
}

/**
 * check if already loaded and saved, or load up
 */
async function loadClassList () {
    try {
        const response = await fetch(props.endpoint);
        const responseJson = await response.json();
        classes.value = responseJson;
    } catch (error) {
        console.warn(error);
        isLoading.value = false;
        hasLoadError.value = true; //hide search bar in the dom
    } finally {
        isLoading.value = false;
    }
}

/**
 * clear the search
 */
function clearInput () {
    query.value = '';
    activeIndex.value = -1;
}

/**
 * how the element animates off
 *
 * @param { DOMElement } element - the element that's animating
 * @param { Function } done - to be called once animation is complete
 *
 */
function checkEachResultIndex () {
    searchResultsEl.value.forEach(searchResult => {
        searchResult.checkIndex();
    });
}

/**
 * animate settings to help context when filtering
 * refer to https://vuejs.org/v2/guide/transitions.html#Staggering-List-Transitions
 * what happens before shown
 *
 * @param { DOMElement } element - the element that's animating
 */
function onBeforeOnEnter (element) {
    gsap.set(element, { opacity: 0, y: 10, height: 0 } );
}

/**
 * how it element animates on
 *
 * @param { DOMElement } element - the element that's animating
 * @param { Function } done - to be called once animation is complete
 */
function onEnter (element, done) {
    let delay = element.dataset.index * 0.025;
    gsap.to(element, 0.3, { opacity: 1, y: 0, height: 'auto', delay: delay, onComplete: done } );
}

/**
 * how the element animates off
 *
 * @param { DOMElement } element - the element that's animating
 * @param { Function } done - to be called once animation is complete
 */
function onLeave (element, done) {
    gsap.to(element, 0.3, { opacity: 0, y: 10, height: 0, onComplete: done } );
}

/**
 * a result has been moused over via #class-selector-search-result
 */
function onMouseOverResult () {
    activeIndex.value = -1; //disable the keyboard selection, mouse takes precedence until search is updated
}

/**
 * a result has been selected via #class-selector-search-result
 * 
 * @param { String }
 * @param { Boolean }
 */
function onSelectedResult (toggledClassItem, isSelected) {
    selectResult(toggledClassItem, isSelected);
}

/**
 * to move to the page the selected search result is on
 * which will have the result highlighted
 * 
 * @param { String }
 * @param { Boolean }
 */
function selectResult (toggledClassItem, isSelected) {
    if (isSelected) {
        selectedClasses.value = selectedClasses.value.filter((selectedClassItem) => selectedClassItem.id !== toggledClassItem.id);
    } else {
        selectedClasses.value = [...selectedClasses.value, toggledClassItem];
    }
}

</script>

<template>
    <div class="class-selector" ref="classSelectorEl">
        <input
            v-if="!hasLoadError"
            v-model="query"
            @input="$event.target.composing = false"
            :readonly="isLoading"
            :placeholder="inputPlaceholder"
            ref="queryInputEl"
            type="text"
            class="search-input"
        />
        <div class="search-results">
            <transition-group
                name="staggered-fade"
                tag="ul"
                v-bind:css="false"
                @before-enter="onBeforeOnEnter"
                @enter="onEnter"
                @enter-cancelled="checkEachResultIndex"
                @leave="onLeave"
                @after-leave="checkEachResultIndex"
                @leave-cancelled="checkEachResultIndex"
            >
                <li v-for="(result, index) in resultsList" :key="result.id" :data-index="index">
                    <class-selector-search-result
                        ref="searchResultsEl"
                        :result="result"
                        :selected-class-ids="selectedClassIds"
                        :query-word-list="queryWordList"
                        :active-index="activeIndex"
                        @on-mouse-over-result="onMouseOverResult"
                        @on-selected-result="onSelectedResult"
                    />
                </li>
            </transition-group>
        </div><!-- .search-results -->
        <div class="selected-classes-container">
            <div v-for="selectedClass in selectedClasses" :key="selectedClass.id" class="selected-class">
                <span>{{ selectedClass.name }}</span>
                <button class="remove-selected-class-button icon-close" :aria-label="`Remove ${selectedClass.name}`" @click.prevent="onRemoveSelected"></button>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>

.class-selector {
    position: relative;
}

.search-input {
    margin: 0;
}

.search-results {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    position: absolute;
    top: 40px;
    width: 100%;
    z-index: 10;
    ul {
        list-style: none;
        margin: 0;
        li {
            border-bottom: 1px solid var(--body-background);
            border-left: 1px solid var(--navigation-hover);
            border-right: 1px solid var(--navigation-hover);
            transform: none !important;
            &:first-child {
                border-top: 1px solid var(--navigation-hover);
            }
            &:last-child {
                border-bottom: 1px solid var(--navigation-hover);
            }
        }
    }
}

</style>

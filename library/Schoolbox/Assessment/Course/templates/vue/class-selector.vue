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

const settingsList =  ref([]);
const isLoaded = ref(false);
const hasLoadError = ref(false);
const query = ref('');
const minQueryLength = 3;
const resultsShownLimit = 6;
const activeIndex = ref(0); //to track which index is selected


/**
 * to notify whether still loading the results or not
 * 
 * @returns { String } loading status
 */
const inputPlaceholder = computed(() => {
    if (isLoaded.value) {
        return 'Search administration settings...';
    } else {
        return 'Loading settings, please wait...'
    }
});

/**
 * only when started typing beyond minQueryLength count and there's actual results
 * 
 * @returns { Boolean } start displaying results or not
 */
const isActive = computed(() => {
    if ((query.value.length >= minQueryLength) && (resultsList.value.length > 0)) {
        return true
    } else {
        return false;
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
 * @returns { Array } all relevant search results, with title matches first over those just with description matches
 */
const resultsList = computed(() => {
    if (query.value.length < minQueryLength) {
        return [];
    }
    return settingsList.value.filter(searchForTitle);
});

/**
 * the settings relevant to the search input and capped to resultsShownLimit
 * 
 * @returns { Array } 
 */
const resultsListLimited = computed(() => {
    if (resultsList.value.length > resultsShownLimit) { //return the capped results
        return resultsList.value.filter((setting, index) => index < resultsShownLimit);
    } else { //otherwise just return the whole list
        return resultsList.value;
    }
});

/**
 * to show the search refine note
 * 
 * @returns { Boolean }
 */
const hasResultsBeydondLimit = computed(() => {
    if (resultsList.value.length > resultsShownLimit) {
        return true
    } else {
        return false;
    }
});

/**
 * to show the count of how many aren't appearing in the search
 * 
 * @returns { Number }
 */
const resultsBeyondLimitCount = computed(() => {
    return resultsList.value.length - resultsShownLimit;
});



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
    loadSettings();
});

/**
 * find all those results that match EACH search word
 * within the the title field
 *
 * @params { Object }
 *
 * @return { Boolean } true if found
 */
function searchForTitle (setting) {
    let wordMatchCount = 0;
    queryWordList.value.forEach(wordInSearch => {
        if (setting.name?.toLowerCase().includes(wordInSearch.toLowerCase())) {
            wordMatchCount++;
        }
    });
    if (wordMatchCount > 0) { //all words have to match
        return true;
    } else {
        return false;
    }
}

/**
 * to be able to select by enter and navigate by up/down arrows
 */
const queryInput = ref(null);
function initKeyboardEvents () {
    document.addEventListener('keyup', (event) => {
        const keyName = event.key;
        const keyOptions = {
            'Enter': () => selectResult(resultsList.value[activeIndex.value].name),
            'ArrowUp': () => decrementActiveIndex(),
            'ArrowDown': () => incrementActiveIndex(),
        }
        if (keyOptions[keyName] && document.activeElement === queryInput.value) { //ensure search input has focus
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
    if (activeIndex.value < (resultsListLimited.value.length-1)) {
        activeIndex.value++;
    }
}

/**
 * to be able to close the menu when something else
 * is selected outside the menu
 */
const adminSearchContainer = ref(null);
function initMouseEvents () {
    document.addEventListener('click', (event) => {
        if (!adminSearchContainer.value.contains(event.target)) {
            clearInput();
        }
    });
}

/**
 * check if already loaded and saved, or load up
 */
async function loadSettings () {
    try {
        const response = await fetch(props.endpoint);
        const responseJson = await response.json();
        settingsList.value = responseJson;
    } catch (error) {
        hasLoadError.value = true; //hide search bar in the dom
    } finally {
        isLoaded.value = true;
    }
}

/**
 * clear the search button selected
 */
function onClearInputSelected () {
    clearInput();
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
const searchResults = ref([]);
function checkEachResultIndex () {
    searchResults.value.forEach(searchResult => {
        searchResult.checkIndex();
    });
}

/**
 * animate settings to help context when filtering
 * refer to https://vuejs.org/v2/guide/transitions.html#Staggering-List-Transitions
 * what happens before shown
 *
 * @param { DOMElement } element - the element that's animating
 *
 */
function onBeforeOnEnter (element) {
    gsap.set(element, { opacity: 0, y: 10, height: 0 } );
}

/**
 * how it element animates on
 *
 * @param { DOMElement } element - the element that's animating
 * @param { Function } done - to be called once animation is complete
 *
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
 *
 */
function onLeave (element, done) {
    gsap.to(element, 0.3, { opacity: 0, y: 10, height: 0, onComplete: done } );
}

/**
 * how the element animates off
 *
 * @param { DOMElement } element - the element that's animating
 * @param { Function } done - to be called once animation is complete
 *
 */
function onAfterLeave (element) {
    searchResults.value.forEach(searchResult => {
        searchResult.test();
    });
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
 */
function onSelectedResult (name) {
    selectResult(name);
}

/**
 * to move to the page the selected search result is on
 * which will have the result highlighted
 * 
 * @param { String }
 */
function selectResult (name) {
    clearInput();
}

</script>

<template>
    <div class="admin-search-container" ref="adminSearchContainer">
        <input
            v-if="!hasLoadError"
            v-model="query"
            @input="$event.target.composing = false"
            :readonly="!isLoaded"
            :placeholder="inputPlaceholder"
            ref="queryInput"
            type="text"
            class=""
        />
        <div>
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
                <li v-for="(result, index) in resultsListLimited" :key="result.name" :data-index="index">
                    <class-selector-search-result
                        ref="searchResults"
                        :result="result"
                        :query-word-list="queryWordList"
                        :active-index="activeIndex"
                        @on-mouse-over-result="onMouseOverResult"
                        @on-selected-result="onSelectedResult"
                    />
                </li>
            </transition-group>
            <aside v-if="isActive && hasResultsBeydondLimit" class="search-refine-note">{{ resultsBeyondLimitCount }} search results not shown, try refining your search further.</aside>
        </div>
    </div>
</template>

<style lang="scss" scoped>

@import 'www/static/css/foundation/utils/_variables.scss';

.admin-search-container {
    position: relative;
    > div {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        position: absolute;
        top: 40px;
        z-index: 10;
    }
}

ul {
    margin: 0;
    list-style: none;
    width: 500px;
    @media only screen and (max-width: 640px) {
        width: 100%;
    }
    li {
        border-bottom: 1px solid $body-bg;
        border-left: 1px solid $navigation-hover;
        border-right: 1px solid $navigation-hover;
        transform: none !important;
        &:first-child {
            border-top: 1px solid $navigation-hover;
        }
        &:last-child {
            border-bottom: 1px solid $navigation-hover;
        }
    }
}

.search-refine-note {
    background-color: #FAFAFA;
    border: 1px solid #EDEDED;
    width: 100%;
    padding: .75rem;
    font-size: 13px;
    font-style: italic;
}

</style>

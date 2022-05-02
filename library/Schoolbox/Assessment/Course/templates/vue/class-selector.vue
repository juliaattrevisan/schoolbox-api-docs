<script setup>

/**
 * To help users add classes to a course faster
 * The search field is used to find classes, then they can be selected to add in
 * Classes can also be manually entered, with pressing enter adding matched results
 */

import { ref, computed, watch, onMounted  } from 'vue'
import ClassSelectorSearchResult from './class-selector-search-result';
import ClassSelectorSelectedClass from './class-selector-selected-class';
import { gsap } from 'gsap/all';


const props = defineProps({
    /**
     * the endpoint to retrieve all the classes from
     */
    endpoint: {
        type: String,
        required: true,
    }
});


/*****************************************************************
 * 
 * Load all the classes
 * 
 ****************************************************************/

const isLoading = ref(true);
const hasLoadError = ref(false);
const classes = ref([]); //all the classes

/**
 * to notify whether still loading the results or not
 * 
 * @returns { String } 
 */
const inputPlaceholder = computed(() => {
    if (isLoading.value) {
        return 'Loading classes, please wait...'
    } else if (hasLoadError.value) {
        return 'Unable to load classes. Please contact your IT support.';
    } else {
        return 'Search for classes or manually enter comma-separated classes...';
    } 
});

onMounted(() => {
    loadClassList();
});

/**
 * check if already loaded and saved, or load up
 */
async function loadClassList () {
    try {
        const response = await fetch(props.endpoint);
        const responseJson = await response.json();
        classes.value = responseJson;
        classes.value.forEach((classItem) => { //add in additional data to help track selection status
            classItem = { ...classItem, isSelectAll: false, isSelected: false };
        });
    } catch (error) {
        console.warn(error);
        isLoading.value = false;
        hasLoadError.value = true; //hide search bar in the dom
    } finally {
        isLoading.value = false;
    }
}


/*****************************************************************
 * 
 * Search for classes
 * 
 ****************************************************************/

const query = ref(''); //the search string to match against
const queryInputEl = ref(null); 
const minQueryLength = 0; //should be able to search for just '3', if we want all of Grade 3  

/**
 * the search string broken into words, to search for each word
 *
 * @returns { Array } the search string broken into single words
 */
const queryWordList = computed(() => {
    const queryTrimmed = query.value.trim();
    const allWords = queryTrimmed.match(/\b(\w+)\b/g) ?? [];
    return allWords.filter(word => word.length > 0);
});

/**
 * the classes relevant to the search input
 * and also the Select All toggle to be able to add / remove ALL search results
 * 
 * @returns { Array } all relevant search results, once we've reached the minQueryLength
 */
const results = computed(() => {
    if (query.value.length === minQueryLength) {
        return [];
    } else if (classes.value.filter(searchForName).length === 1) { //don't add in the Select All for just one result
        return classes.value.filter(searchForName)
    } else if (classes.value.filter(searchForName).length > 1) { 
        return [{ id: -1, name: 'Add all classes', isSelectAll: true, isSelected: false }, ...classes.value.filter(searchForName)]; 
    } else {
        return [];
    }
});

/**
 * pull out select all option for adding / removing classes 
 * 
 * @returns { Array } all relevant search results, once we've reached the minQueryLength
 */
const resultsNoSelectAll = computed(() => {
    return results.value.filter((result) => !result.isSelectAll);
});

/**
 * find all those results that match EACH search word
 * within the the title field
 *
 * @params { Object }
 *
 * @return { Boolean } true if found
 */
function searchForName (classItem) {
    let wordMatchCount = 0;
    queryWordList.value.forEach(wordInSearch => {
        if (classItem.name?.toLowerCase().includes(wordInSearch.toLowerCase())) {
            wordMatchCount++;
        }
    });
    return wordMatchCount > 0 ? true : false; //if a word matches
}


/*****************************************************************
 * 
 * Keyboard / Mouse events
 * 
 ****************************************************************/

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
    addAllResults();
    clearInput();
}

const classSelectorEl = ref(null); 

/**
 * to be able to close the search results
 * when selection outside is made
 */
function initMouseEvents () {
    document.addEventListener('click', (event) => {
        if (classSelectorEl.value && !classSelectorEl.value.contains(event.target)) {
            clearInput();
        }
    });
}

/**
 * clear the search
 */
function clearInput () {
    query.value = '';
}


/*****************************************************************
 * 
 * Animating the search results
 * 
 ****************************************************************/

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
    const delay = element.dataset.index * 0.025;
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


/*****************************************************************
 * 
 * Adding / Removing classes 
 * 
 ****************************************************************/

const selectedClasses = ref([]); //the classes currently selected

/**
 * To determine the behaviour of the 'Select all classes' button
 * 
 * @returns { String } 
 */
const selectedClassIds = computed(() => {
    const selectedClassIdList = selectedClasses.value.map((selectedClasss) => selectedClasss.id);
    return selectedClassIdList.join(',');
});

/**
 * a result has been selected via #class-selector-search-result
 * 
 * @param { Object }
 * @param { Boolean }
 */
function onToggleResult (toggledClassItem) {
    toggleResult(toggledClassItem);
}

/**
 * add the class to the selected list
 * 
 * @param { Object }
 * @param { Boolean }
 */
function toggleResult (toggledClassItem) {
    if (toggledClassItem.isSelected) { //pull out
        toggledClassItem.isSelected = false;
        selectedClasses.value = selectedClasses.value.filter((selectedClassItem) => selectedClassItem.id !== toggledClassItem.id);
    } else { //otherwise put in
        toggledClassItem.isSelected = true;
        selectedClasses.value = [...selectedClasses.value, toggledClassItem];
    }
}

/**
 * To determine the behaviour of the 'Select all classes' button
 * 
 * @returns { Boolean } 
 */
const isAllResultsSelected = computed(() => {
    if (selectedClasses.value.length > 0) {
        const alreadySelectedClasses = resultsNoSelectAll.value.filter((resultItem) => resultItem.isSelected); //check if the class is in the list already
        return alreadySelectedClasses.length === resultsNoSelectAll.value.length ? true : false;
    } else {
        return false;
    }
});

/**
 * To add the 'indeterminate' class to the select all checkbox
 * 
 * @returns { Boolean } 
 */
const isSomeResultsSelected = computed(() => {
    if (selectedClasses.value.length > 0) {
        const alreadySelectedClasses = resultsNoSelectAll.value.filter((resultItem) => resultItem.isSelected); //check if the class is in the list already
        return alreadySelectedClasses.length >= 1 && alreadySelectedClasses.length !== resultsNoSelectAll.value.length ? true : false;
    } else {
        return false;
    }
});

/**
 * to add / remove all the currently matched search classes
 */
function onToggleAllResults () {
    if (isAllResultsSelected.value) {
        removeAllResults();
    } else {
        addAllResults();
    }
}

/**
 * pull all results out 
 */
function removeAllResults () {
    const matchedSelectedClasses = resultsNoSelectAll.value.filter((resultItem) => { //check if the class is in the list already
        return selectedClasses.value.some((selectedClassItem) => selectedClassItem.id === resultItem.id); //add in those that match
    });
    matchedSelectedClasses.forEach((classItem) => classItem.isSelected = false); //set false on all
    selectedClasses.value = selectedClasses.value.filter((selectedClassItem) => selectedClassItem.isSelected); //and then remove those there were just unset  
}

/**
 * add all results in 
 */
function addAllResults () {
    const newSelectedClasses = resultsNoSelectAll.value.filter((resultItem) => { //check if the class isn't in the list already
        return !selectedClasses.value.some((selectedClassItem) => selectedClassItem.id === resultItem.id); //don't add in those that match
    });
    newSelectedClasses.forEach((classItem) => classItem.isSelected = true);
    selectedClasses.value = [...selectedClasses.value, ...newSelectedClasses];
}

/**
 * to remove a class from selection
 * 
 * @param { Object }
 */
function onRemoveClassSelected (classItem) {
    classItem.isSelected = false;
    selectedClasses.value = selectedClasses.value.filter((selectedClassItem) => selectedClassItem.id !== classItem.id);
}

</script>

<template>
    <div class="class-selector" ref="classSelectorEl">
        <label class="required">Class(es)</label>
        <input id="import_folders" type="hidden" tabindex="-1" :value="selectedClassIds" />
        <input
            v-model="query"
            ref="queryInputEl"
            type="text"
            class="search-input"
            :readonly="isLoading || hasLoadError"
            :placeholder="inputPlaceholder"
            @input="$event.target.composing = false"
        />
        <div class="search-results-container">
            <div class="search-results">
                <transition-group
                    tag="ul"
                    :css="false"
                    @before-enter="onBeforeOnEnter"
                    @enter="onEnter"
                    @leave="onLeave"
                >
                    <li v-for="(result, index) in results" :key="result.id" :data-index="index">
                        <class-selector-search-result
                            :result="result"
                            :is-all-results-selected="isAllResultsSelected"
                            :is-some-results-selected="isSomeResultsSelected"
                            :query-word-list="queryWordList"
                            @toggle="onToggleResult"
                            @toggle-all="onToggleAllResults"
                        />
                    </li>
                </transition-group>
            </div><!-- .search-results -->
        </div><!-- .search-results-container -->
        <div class="selected-classes-container">
            <class-selector-selected-class 
                v-for="selectedClass in selectedClasses" 
                :key="selectedClass.id"  
                :class-item="selectedClass"  
                @remove="onRemoveClassSelected"  
            />
        </div><!-- .selected-classes-container -->
    </div>
</template>

<style lang="scss" scoped>
.radiolist {
    display: flex;
    gap: 1rem;
    margin: 0;
    padding: 0;
    .option-list {
        display: flex;
        gap: 1rem;
        margin: 0;
    }
}


.search-input {
    margin: 0; //so the .search-results-container is jammed up on the input
    &::placeholder {
        font-style: italic;
    }
}

.search-results-container {
    position: relative; //to give the .search-result position: absolute context
}

.search-results {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    max-height: 500px;
    overflow: auto;
    position: absolute;
    top: 0;
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

.selected-classes-container {
    border: 1px solid var(--form-border-color);
    border-top: none;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
    min-height: 50px;
    padding: 0.5rem;
}

</style>

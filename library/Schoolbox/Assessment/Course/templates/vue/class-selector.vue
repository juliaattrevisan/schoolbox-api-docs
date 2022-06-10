<script setup>

/**
 * To help users add classes to a course faster
 * The search field is used to find classes, then they can be selected to add in
 * Classes can also be manually entered, with pressing enter adding matched results
 */

import { ref, computed, onMounted  } from 'vue'
import ClassSelectorSelectAll from './class-selector-select-all';
import ClassSelectorSearchResult from './class-selector-search-result';
import ClassSelectorSelectedClass from './class-selector-selected-class';


const props = defineProps({
    /**
     * the endpoint to retrieve all the classes from
     */
    endpoint: {
        type: String,
        required: true,
    },
    /**
     * for the Modify Course page, the class-selector will be disabled but it should contain the 
     * Class that was previously selected. The class will be one we have access the Modify Course page from
     */
    previouslySelectedClass: { 
        type: Object,
        required: false,
        default: {},
    },
});


/*****************************************************************
 * 
 * Load all the classes
 * 
 ****************************************************************/

const isLoading = ref(true);
const hasLoadError = ref(false);
const classes = ref([]); //all the classes data


/**
 * a class has already been previously selected, so the class-selector is not enabled
 * It's disabled from outside the Vue component within library/Schoolbox/Assessment/Course/templates/import.twig
 * 
 * @returns { Boolean } 
 */
const hasPreviouslySelectedClass = computed(() => {
    return props.previouslySelectedClass.id !== undefined;
});

/**
 * 
 * 
 * @returns { Boolean } 
 */
const isClassSelectorActive = computed(() => {
    return props.previouslySelectedClass.id === undefined;
});

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
    if (hasPreviouslySelectedClass.value) { //when this is set, the class-selector is disabled from a parent outside this Vue component
        addPreviouslySelectedClass();
    } else { //otherwise we assume we can edit the class selector
        loadClassList();
    }
});

/**
 * just show the previously selected class on the disabled class selector
 */
function addPreviouslySelectedClass () {
    selectedClasses.value = [props.previouslySelectedClass];
}

/**
 * load all the classes for searching
 */
async function loadClassList () {
    try {
        const response = await fetch(props.endpoint);
        const responseJson = await response.json();
        classes.value = responseJson;
        classes.value.forEach((classItem) => { //add in additional data to help track selection status
            classItem = { ...classItem, isSelected: false };
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
const minQueryLength = 1; //should be able to search for just '3', if we want all of Grade 3  
const CAPPED_RESULTS_LENGTH = 100; //the maximum amount of results to show
const isResultsCapped = ref(false); //to show a 'refine your search' note
const resultsNotShownCount = ref(0); //a total of the results not visible in the list

/**
 * the search string broken into words, to search for each word
 *
 * @returns { Array } the search string broken into single words
 */
const queryWordList = computed(() => {
    const queryTrimmed = query.value.trim(); //remove any front or back white space
    const re = /\s*(?:,|$)\s*/ //adapted from https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/split#removing_spaces_from_a_string
    const commaSeparated = queryTrimmed.split(re) ?? []; //split the string into a comma separated list
    return commaSeparated.filter(word => word.length > 0); //and ensure each contains a character
});

/**
 * the classes found relevant to the search input, capped to the maximum 
 * amount of results to be shown
 * 
 * @returns { Array } all relevant search results, once we've reached the minQueryLength
 */
const results = computed(() => {
    isResultsCapped.value = false;
    if (query.value.length < minQueryLength) {
        return [];
    } else if (classes.value.filter(searchForName).length > 0) { 
        const fullResults = classes.value.filter(searchForName);
        if (fullResults.length > CAPPED_RESULTS_LENGTH) { //too many results, so cap them
            isResultsCapped.value = true; //to display the results are capped note
            resultsNotShownCount.value = fullResults.length - CAPPED_RESULTS_LENGTH; //and how many are actually capped
            return fullResults.slice(0, CAPPED_RESULTS_LENGTH);
        } else {//otherwise just return them all
            return fullResults;
        }
    } else {
        return [];
    }
});

/**
 * show select all option for adding / removing classes only if there's 
 * more than one search result
 * 
 * @returns { Boolean } 
 */
const showSelectAll = computed(() => {
    return results.value.length > 1;
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
 * Adding / Removing classes 
 * 
 ****************************************************************/

const selectedClasses = ref([]); //the classes currently selected

/**
 * determine display of elements based on whether any classes are selected
 * 
 * @returns { Boolean } all relevant search results, once we've reached the minQueryLength
 */
const hasSelectedClasses = computed(() => {
    return selectedClasses.value.length > 0;
});

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
function onSelectResult (toggledClass) {
    toggleResult(toggledClass);
}

/**
 * add the class to the selected list
 * 
 * @param { Object }
 * @param { Boolean }
 */
function toggleResult (toggledClass) {
    if (toggledClass.isSelected) { //pull out
        toggledClass.isSelected = false;
        selectedClasses.value = selectedClasses.value.filter((selectedClass) => selectedClass.id !== toggledClass.id);
    } else { //otherwise put in
        toggledClass.isSelected = true;
        selectedClasses.value = [...selectedClasses.value, toggledClass];
    }
}

/**
 * To determine the behaviour of the 'Select all classes' button
 * 
 * @returns { Boolean } 
 */
const isAllResultsSelected = computed(() => {
    if (selectedClasses.value.length > 0) {
        const alreadySelectedClasses = results.value.filter((resultItem) => resultItem.isSelected); //check if the class is in the list already
        return alreadySelectedClasses.length === results.value.length ? true : false;
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
        const alreadySelectedClasses = results.value.filter((resultItem) => resultItem.isSelected); //check if the class is in the list already
        return alreadySelectedClasses.length >= 1 && alreadySelectedClasses.length !== results.value.length ? true : false;
    } else {
        return false;
    }
});

/**
 * to add / remove all the currently matched search classes
 */
function onSelectAllResults () {
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
    const matchedSelectedClasses = results.value.filter((matchedSelectedClass) => { //check if the class is in the list already
        return selectedClasses.value.some((selectedClass) => selectedClass.id === matchedSelectedClass.id); //add in those that match
    });
    matchedSelectedClasses.forEach((matchedSelectedClass) => matchedSelectedClass.isSelected = false); //set false on all
    selectedClasses.value = selectedClasses.value.filter((selectedClass) => selectedClass.isSelected); //and then remove those there were just unset  
}

/**
 * add all results in 
 */
function addAllResults () {
    const newSelectedClasses = results.value.filter((newSelectedClass) => { //check if the class isn't in the list already
        return !selectedClasses.value.some((selectedClass) => selectedClass.id === newSelectedClass.id); //don't add in those that match
    });
    newSelectedClasses.forEach((newSelectedClass) => newSelectedClass.isSelected = true);
    selectedClasses.value = [...selectedClasses.value, ...newSelectedClasses];
}

/**
 * to remove a class from selection
 * 
 * @param { Object }
 */
function onRemoveClassSelected (removedClass) {
    removedClass.isSelected = false;
    selectedClasses.value = selectedClasses.value.filter((selectedClass) => selectedClass.id !== removedClass.id);
}

/**
 * clear all classes
 */
function onClearAllSelected () {
    selectedClasses.value.forEach((selectedClass) => selectedClass.isSelected = false);
    selectedClasses.value = [];
}

</script>

<template>
    <div class="class-selector" ref="classSelectorEl">
        <label class="required">Class(es)</label>
        <input id="import_folders" name="import_folders" type="hidden" tabindex="-1" :value="selectedClassIds" />
        <input
            v-if="isClassSelectorActive"
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
                <ul>
                    <li v-if="isResultsCapped" class="search-refine-note">{{ resultsNotShownCount }} search results not shown, try refining your search further.</li>
                    <li v-if="showSelectAll">
                        <class-selector-select-all
                            :is-all-results-selected="isAllResultsSelected"
                            :is-some-results-selected="isSomeResultsSelected"
                            @select-all="onSelectAllResults"
                        />
                    </li>
                    <li v-for="result in results" :key="result.id">
                        <class-selector-search-result
                            :result="result"
                            :is-all-results-selected="isAllResultsSelected"
                            :is-some-results-selected="isSomeResultsSelected"
                            :query-word-list="queryWordList"
                            @select="onSelectResult"
                        />
                    </li>
                </ul>
            </div><!-- .search-results -->
        </div><!-- .search-results-container -->
        <div v-if="hasSelectedClasses" :class="[{ 'selected-classes-container__disabled': hasPreviouslySelectedClass }, 'selected-classes-container']">
            <class-selector-selected-class 
                v-for="selectedClass in selectedClasses" 
                :key="selectedClass.id"  
                :class-item="selectedClass"
                :is-class-selector-active="isClassSelectorActive"
                @remove="onRemoveClassSelected"  
            />
        </div><!-- .selected-classes-container -->
        <div class="extra-actions-container">
            <button v-if="isClassSelectorActive && hasSelectedClasses" class="button icon-delete clear-all-button" aria-label="Clear all Classes" @click.prevent="onClearAllSelected">Clear all Classes</button>
        </div>
    </div>
</template>

<style lang="scss" scoped>

.class-selector {
    margin-bottom: 1rem;
}

.search-input {
    margin: 0; //so the .search-results-container is jammed up on the input
    &::placeholder {
        font-style: italic;
    }
    &:placeholder-shown { //to show there's more text in the placeholder on smaller screens
        text-overflow: ellipsis;
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

.search-refine-note {
    background-color: #FAFAFA;
    padding: 0.75rem;
    font-style: italic;
}

.selected-classes-container {
    border: 1px solid var(--form-border-color);
    display: flex;
    flex-wrap: wrap;
    margin-top: -1px; //to sit against .search-input
    min-height: 50px;
    padding: 0.5rem;
}

    .selected-classes-container__disabled {
        opacity: 0.7;
    }

.extra-actions-container {
    display: flex;
    justify-content: flex-end;
}

.clear-all-button {
    font-size: 0.825rem;
    &:before {
        font-size: 0.825rem;
    }
}

</style>

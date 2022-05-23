<script>

/**
 * To help users find admin settings faster (hopefully!)
 * Search field is used to filter the results, then the relevant
 * search result can be selected to move to the relevant page/setting
 *
 */

import AdminSettingsSearchResult from './admin-settings-search-result';
import { gsap } from 'gsap/all';

const SB_SETTINGS_LIST = 'sb_settingsList';


export default {

    components: {
        AdminSettingsSearchResult
    },

    data () {
        return {
            settingsList: [],
            isLoaded: false,
            hasLoadError: false,
            query: '',
            minQueryLength: 3,
            resultsShownLimit: 6,
            activeIndex: 0 //to track which index is selected
        }
      },

    computed: {

        /**
         * to notify whether still loading the results or not
         * 
         * @returns { String } loading status
         */
        inputPlaceholder () {
            if (this.isLoaded) {
                return 'Search administration settings...';
            } else {
                return 'Loading settings, please wait...'
            }
        },

        /**
         * only when started typing beyond minQueryLength count and there's actual results
         * 
         * @returns { Boolean } start displaying results or not
         */
        isActive () {
            if ((this.query.length >= this.minQueryLength) && (this.resultsList.length > 0)) {
                return true
            } else {
                return false;
            }
        },

        /**
         * the search string broken into words, to search for each word
         *
         * @returns { Array } the search string broken into single words
         */
        queryWordList () {
            const allWords = this.query.match(/\b(\w+)\b/g);
            if (allWords && allWords.length > 0) {
                const onlyWordsAboveTwoLetters = allWords.filter(word => word.length > 1);
                return onlyWordsAboveTwoLetters;
            } else {
                return [];
            }
        },

        /**
         * the settings relevant to the search input
         * 
         * @returns { Array } all relevant search results, with title matches first over those just with description matches
         */
        resultsList () {
            if (this.query.length < this.minQueryLength) {
                return [];
            }
            const matchedTitleList = this.settingsList.filter(this.searchForTitle);
            const matchedNameAndDescriptionList = this.settingsList.filter(this.searchForNameAndDescription);
            return matchedTitleList.concat(matchedNameAndDescriptionList); //so those that have title matches take precedence
        },

        /**
         * the settings relevant to the search input and capped to resultsShownLimit
         * 
         * @returns { Array } 
         */
        resultsListLimited () {
            if (this.resultsList.length > this.resultsShownLimit) { //return the capped results
                return this.resultsList.filter((setting, index) => index < this.resultsShownLimit);
            } else { //otherwise just return the whole list
                return this.resultsList;
            }
        },

        /**
         * to show the search refine note
         * 
         * @returns { Boolean }
         */
        hasResultsBeydondLimit () {
            if (this.resultsList.length > this.resultsShownLimit) {
                return true
            } else {
                return false;
            }
        },

        /**
         * to show the count of how many aren't appearing in the search
         * 
         * @returns { Number }
         */
        resultsBeyondLimitCount () {
            return this.resultsList.length - this.resultsShownLimit;
        }

    },

    watch: {

        /**
         * reset the active index when the list changes as the index may become out of range
         */
        resultsList (newValue, oldValue) {
            if ((newValue.length !== oldValue.length) && (this.activeIndex > 0)) {
                this.activeIndex = 0;
            }
        }

    },

    mounted () {
        this.initKeyboardEvents();
        this.initMouseEvents();
        this.loadSettings();
    },

    methods: {

        /**
         * find all those results that match EACH search word
         * within the the title field
         *
         * @params { Object }
         *
         * @return { Boolean } true if found
         */
        searchForTitle (setting) {
            let wordMatchCount = 0;
            this.queryWordList.forEach(wordInSearch => {
                if (setting.title?.toLowerCase().includes(wordInSearch.toLowerCase())) {
                    wordMatchCount++;
                }
            });
            if (wordMatchCount === this.queryWordList.length) { //all words have to match
                return true;
            } else {
                return false;
            }
        },

        /**
         * find all those results that match EACH search word
         * within the name and description fields
         *
         * @params { Object }
         *
         * @return { Boolean } true if found
         */
        searchForNameAndDescription (setting) {
            let wordMatchCount = 0;
            this.queryWordList.forEach(wordInSearch => {
                if ((setting.name?.toLowerCase().includes(wordInSearch.toLowerCase())
                    || setting.description?.toLowerCase().includes(wordInSearch.toLowerCase()))
                    && (!setting.title?.toLowerCase().includes(wordInSearch.toLowerCase()))) {
                    wordMatchCount++;
                }
            });
            if (wordMatchCount === this.queryWordList.length) { //all words have to match
                return true;
            } else {
                return false;
            }
        },

        /**
         * to be able to select by enter and navigate by up/down arrows
         */
        initKeyboardEvents () {
            document.addEventListener('keyup', (event) => {
                const keyName = event.key;
                const keyOptions = {
                    'Enter': () => this.onEnterKeyPressed(),
                    'ArrowUp': () => this.decrementActiveIndex(),
                    'ArrowDown': () => this.incrementActiveIndex(),
                }
                if (keyOptions[keyName] && document.activeElement === this.$refs.queryInput) { //ensure search input has focus
                    keyOptions[keyName]();
                }
            });
        },

        /**
         * an alternative to mouse press, 
         * select the currently active search result 
         */
        onEnterKeyPressed () {
            if (this.resultsList.length > 0) { //only if there's results to select
                this.selectResult(this.resultsList[this.activeIndex].url);
            }
        },

        /**
         * to be able to move up and down through the selections
         * with the arrow keys
         */
        decrementActiveIndex () {
            if (this.activeIndex > 0) {
                this.activeIndex--;
            }
        },

        /**
         * to be able to move up and down through the selections
         * with the arrow keys
         */
        incrementActiveIndex () {
            if (this.activeIndex < (this.resultsListLimited.length-1)) {
                this.activeIndex++;
            }
        },

        /**
         * to be able to close the menu when something else
         * is selected outside the menu
         */
        initMouseEvents () {
            document.addEventListener('click', (event) => {
                if (!this.$el.contains(event.target)) {
                    this.clearInput();
                }
            });
        },

        /**
         * check if already loaded and saved, or load up
         */
        loadSettings () {
            if (sessionStorage.getItem(SB_SETTINGS_LIST)) {
                this.loadSessionSettings();
            } else {
                this.loadRemoteSettings();
            }
        },

        /**
         * pull the settings from local storage
         */
        loadSessionSettings () {
            const sessionStorageSettingsList  = sessionStorage.getItem(SB_SETTINGS_LIST);
            this.settingsList = JSON.parse(sessionStorageSettingsList); //conver the saved string var back to JSON
            this.isLoaded = true;
        },

        /**
         * load the settings for possible retrieval
         */
        async loadRemoteSettings () {
            try {
                const response = await fetch('/adminv2/setting/search');
                const responseJson = await response.json();
                this.settingsList = responseJson;
                sessionStorage.setItem(SB_SETTINGS_LIST, JSON.stringify(this.settingsList)); //session storage vars need to be saved as strings only
            } catch (error) {
                this.hasLoadError = true; //hide search bar in the dom
            } finally {
                this.isLoaded = true;
            }
        },

        /**
         * clear the search button selected
         */
        onClearInputSelected () {
            this.clearInput();
        },

        /**
         * clear the search
         */
        clearInput () {
            this.query = '';
            this.activeIndex = -1;
        },

        /**
         * how the element animates off
         *
         * @param { DOMElement } element - the element that's animating
         * @param { Function } done - to be called once animation is complete
         *
         */
        checkEachResultIndex () {
            this.$refs.searchResults.forEach(searchResult => {
                searchResult.checkIndex();
            });
        },

        /**
         * animate settings to help context when filtering
         * refer to https://vuejs.org/v2/guide/transitions.html#Staggering-List-Transitions
         * what happens before shown
         *
         * @param { DOMElement } element - the element that's animating
         *
         */
        onBeforeOnEnter (element) {
            gsap.set(element, { opacity: 0, y: 10, height: 0 } );
        },

        /**
         * how it element animates on
         *
         * @param { DOMElement } element - the element that's animating
         * @param { Function } done - to be called once animation is complete
         *
         */
        onEnter (element, done) {
            let delay = element.dataset.index * 0.025;
            gsap.to(element, 0.3, { opacity: 1, y: 0, height: 'auto', delay: delay, onComplete: done } );
        },

        /**
         * how the element animates off
         *
         * @param { DOMElement } element - the element that's animating
         * @param { Function } done - to be called once animation is complete
         *
         */
        onLeave (element, done) {
            gsap.to(element, 0.3, { opacity: 0, y: 10, height: 0, onComplete: done } );
        },

        /**
         * how the element animates off
         *
         * @param { DOMElement } element - the element that's animating
         * @param { Function } done - to be called once animation is complete
         *
         */
        onAfterLeave (element) {
            this.$refs.searchResults.forEach(searchResult => {
                searchResult.test();
            });
        },

        /**
         * a result has been moused over via #admin-settings-search-result
         */
        onMouseOverResult () {
            this.activeIndex = -1; //disable the keyboard selection, mouse takes precedence until search is updated
        },

        /**
         * a result has been selected via #admin-settings-search-result
         * 
         * @param { String }
         */
        onSelectedResult (url) {
            this.selectResult(url);
        },

        /**
         * to move to the page the selected search result is on
         * which will have the result highlighted
         * 
         * @param { String }
         */
        selectResult (url) {
            this.clearInput();
            window.location.href = url;
            if (window.location.href != url) { //only dispatch if on the same page, otherwise handled in page load in #moduleConfig.twig
                var event = new CustomEvent('admin-settings-search__on-result-selected', {
                    detail: {
                        url: url
                    }
                });
                document.dispatchEvent(event);
            }
        }

    }

}

</script>

<template>
    <div class="admin-search-container">
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
                    <admin-settings-search-result
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

<script>

/**
 * To help users find admin settings faster (hopefully!)
 * Search field is used to filter the results, then the relevant
 * search result can be selected to move to the relevant page/setting
 *
 */

import AdminSettingsSearchResult from './admin-settings-search-result';
import { fetchAsJson } from '@shared/fetch/fetch';
import { gsap } from 'gsap/all'

export default {

    name: 'admin-settings-search',

	components: {
		AdminSettingsSearchResult
	},

    data () {
    	return {
			settingsList: [],
            isLoaded: false,
			hasLoadError: false,
			searchQuery: '',
			minSearchQueryLength: 3,
            searchResultsShownLimit: 6,
            activeIndex: 0 //to track which index is selected
    	}
  	},

    computed: {

        /**
         * to notify whether still loading the results or not
         * 
         * @returns {String} loading status
         */
        searchInputPlaceholder () {
			if (this.isLoaded) {
                return 'Search administration settings...';
            } else {
                return 'Loading settings, please wait...'
            }
		},

        /**
         * only when started typing beyond minSearchQueryLength count and there's actual results
         * 
         * @returns {String} start displaying results or not
         */
        isSearchActive () {
			if ((this.searchQuery.length >= this.minSearchQueryLength) && (this.filteredSearchList.length > 0)) {
                return true
            } else {
                return false;
            }
		},

        /**
         * the search string broken into words, to search for each word
         *
         * @returns {Array} the search string broken into single words
         */
        searchStringWordList () {
			const allWords = this.searchQuery.match(/\b(\w+)\b/g);
            const onlyWordsAboveTwoLetters = allWords.filter(word => word.length > 1);
            return onlyWordsAboveTwoLetters;
		},

        /**
         * the settings relevant to the search input
         * 
         * @returns {Array} all relevant search results, with title matches first over those just with description matches
         */
		filteredSearchList () {
			if (this.searchQuery.length < this.minSearchQueryLength) {
				return [];
			}
            const matchedTitleList = this.settingsList.filter(this.searchForTitle);
            const matchedNameAndDescriptionList = this.settingsList.filter(this.searchForNameAndDescription);
            return matchedTitleList.concat(matchedNameAndDescriptionList); //so those that have title matches take precedence
		},

        /**
         * the settings relevant to the search input and capped to searchResultsShownLimit
         * 
         * @returns {Array} 
         */
		filteredSearchListLimited () {
            if (this.filteredSearchList.length > this.searchResultsShownLimit) { //return the capped results
                return this.filteredSearchList.filter((setting, index) => index < this.searchResultsShownLimit);
            } else { //otherwise just return the whole list
                return this.filteredSearchList;
            }
		},

        /**
         * to show the search refine note
         * 
         * @returns {Boolean}
         */
        hasResultsBeydondSearchResultsShownLimit () {
			if (this.filteredSearchList.length > this.searchResultsShownLimit) {
				return true
			} else {
                return false;
            }
		},

        /**
         * to show the count of how many aren't appearing in the search
         * 
         * @returns {Number}
         */
        resultsBeydondSearchResultsShownLimitCount () {
			return this.filteredSearchList.length - this.searchResultsShownLimit;
		}

	},

    watch: {

        /**
         * reset the active index when the list changes as the index may become out of range
         */
		filteredSearchList (newValue, oldValue) {
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
         * @params {Object}
         *
         * @return {Boolean} true if found
         */
        searchForTitle (setting) {
            let wordMatchCount = 0;
            this.searchStringWordList.forEach(wordInSearch => {
                if (setting.title?.toLowerCase().includes(wordInSearch.toLowerCase())) {
                    wordMatchCount++;
                }
            });
            if (wordMatchCount === this.searchStringWordList.length) { //all words have to match
                return true;
            } else {
                return false;
            }
		},

        /**
         * find all those results that match EACH search word
         * within the name and description fields
         *
         * @params {Object}
         *
         * @return {Boolean} true if found
         */
        searchForNameAndDescription (setting) {
            let wordMatchCount = 0;
            this.searchStringWordList.forEach(wordInSearch => {
                if ((setting.name?.toLowerCase().includes(wordInSearch.toLowerCase())
                    || setting.description?.toLowerCase().includes(wordInSearch.toLowerCase()))
                    && (!setting.title?.toLowerCase().includes(wordInSearch.toLowerCase()))) {
                    wordMatchCount++;
                }
            });
            if (wordMatchCount === this.searchStringWordList.length) { //all words have to match
                return true;
            } else {
                return false;
            }
		},

        /**
         * to be able to select by enter and navigate by up/down arrows
         *
         */
		initKeyboardEvents () {
			document.addEventListener('keyup', (event) => {
                const keyName = event.key;

                if (keyName === 'Enter') {
                    this.selectResult(this.filteredSearchList[this.activeIndex].url);

                } else if (keyName === 'ArrowUp') {
                    this.decrementActiveIndex();

                } else if (keyName === 'ArrowDown') {
                    this.incrementActiveIndex();

                }
            });
		},

        /**
         * to be able to move up and down through the selections
         * with the arrow keys
         *
         */
        decrementActiveIndex () {
            if (this.activeIndex > 0) {
                this.activeIndex--;
            }
		},

        /**
         * to be able to move up and down through the selections
         * with the arrow keys
         *
         */
        incrementActiveIndex () {
            if (this.activeIndex < (this.filteredSearchListLimited.length-1)) {
			    this.activeIndex++;
            }
		},

        /**
         * to be able to close the menu when something else
         * is selected outside the menu
         *
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
         *
         */
		loadSettings () {
			if (localStorage.getItem('sb_settingsList')) {
                this.loadLocalSettings();
            } else {
                this.loadRemoteSettings();
            }
		},

        /**
         * pull the settings from local storage
         *
         */
		loadLocalSettings () {
            const localStorageSettingsList  = localStorage.getItem('sb_settingsList');
            this.settingsList = JSON.parse(localStorageSettingsList);
            this.isLoaded = true;
        },

        /**
         * load the settings for possible retrieval
         *
         */
		loadRemoteSettings () {
			fetchAsJson('/adminv2/setting/search')
			.then((response) => {
				this.settingsList = response;
                localStorage.setItem('sb_settingsList', JSON.stringify(this.settingsList));
				this.isLoaded = true;
			}).catch((error) => {
				this.hasLoadError = true; //hide search bar in the dom
			});
		},

        /**
         * clear the search button selected
         *
         */
        onClearInputSelected () {
			this.clearInput();
		},

        /**
         * clear the search
         *
         */
		clearInput () {
			this.searchQuery = '';
            this.activeIndex = -1;
		},

        /**
         * how the element animates off
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
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
         * @param {DOMElement} element - the element that's animating
         *
         */
        onBeforeOnEnter (element) {
            gsap.set(element, { opacity: 0, y: 10, height: 0 } );
        },

        /**
         * how it element animates on
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
         *
         */
        onEnter (element, done) {
            let delay = element.dataset.index * 0.025;
            gsap.to(element, 0.3, { opacity: 1, y: 0, height: 'auto', delay: delay, onComplete: done } );
        },

        /**
         * how the element animates off
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
         *
         */
        onLeave (element, done) {
            gsap.to(element, 0.3, { opacity: 0, y: 10, height: 0, onComplete: done } );
        },

        /**
         * how the element animates off
         *
         * @param {DOMElement} element - the element that's animating
         * @param {Function} done - to be called once animation is complete
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
         */
        onSelectedResult (url) {
            this.selectResult(url);
		},

        /**
         * to move to the page the selected search result is on
         * which will have the result highlighted
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
            v-model="searchQuery"
            :readonly="!isLoaded"
            :placeholder="searchInputPlaceholder"
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
                <li v-for="(result, index) in filteredSearchListLimited" :key="result.name" :data-index="index">
                    <admin-settings-search-result
                        ref="searchResults"
                        :result="result"
                        :search-string-word-list="searchStringWordList"
                        :active-index="activeIndex"
                        @on-mouse-over-result="onMouseOverResult"
                        @on-selected-result="onSelectedResult"
                    />
                </li>
            </transition-group>
            <aside v-if="isSearchActive && hasResultsBeydondSearchResultsShownLimit" class="search-refine-note">{{ resultsBeydondSearchResultsShownLimitCount }} search results not shown, try refining your search further.</aside>
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

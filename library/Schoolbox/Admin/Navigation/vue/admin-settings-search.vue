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
			currentSearchString: '',
			minSearchStringLength: 3,
            searchResultsShownLimit: 6
    	};
  	},

    computed: {

        /**
         * no notify whether still loading the results or not
         */
        searchInputPlaceholder () {
			if (!this.isLoaded) {
                return 'Loading settings, please wait...'
            } else {
                return 'Search admin settings..'; 
            }
		},

        /**
         * only when started typing beyond minSearchStringLength count and there's actual results
         */
        isSearchActive () {
			if ((this.currentSearchString.length >= this.minSearchStringLength) && (this.filteredSearchList.length > 0)) {
                return true
            } else {
                return false;
            }
		},

        /**
         * the settings relevant to the search input
         */
		filteredSearchList () {
			if (this.currentSearchString.length < this.minSearchStringLength) { 
				return [];
			}
			return this.settingsList.filter(setting => { //retrieve all settings that match
				if (setting.title.toLowerCase().includes(this.currentSearchString.toLowerCase())
					|| setting.name.toLowerCase().includes(this.currentSearchString.toLowerCase())
					|| setting.description.toLowerCase().includes(this.currentSearchString.toLowerCase())) {
					return true;
				} else {
					return false;
				}
			});
		},

        /**
         * the settings relevant to the search input and capped to searchResultsShownLimit
         */
		filteredSearchListLimited () {
            if (this.filteredSearchList.length > this.searchResultsShownLimit) { //return the capped results
                return this.filteredSearchList.filter((setting, index) => index < this.searchResultsShownLimit);
            } else { //otherwise just return
                return this.filteredSearchList;
            }

		},

        /**
         * to show the search refine note
         */
        hasResultsBeydondSearchResultsShownLimit () {
			if (this.filteredSearchList.length > this.searchResultsShownLimit) { 
				return true
			} else {
                return false;
            }
		},

        /**
         * to show how many aren't appearing in the search 
         */
        resultsBeydondSearchResultsShownLimitCount () {
			return this.filteredSearchList.length - this.searchResultsShownLimit;
		} 

	},

    mounted () {
        this.loadSettings();
    },

    methods: {

        /**
         * load the settings for possible retrieval
         *
         */
		loadSettings () {
			fetchAsJson('/adminv2/setting/search')
			.then((response) => {
				this.settingsList = response;
				this.isLoaded = true;
			}).catch((error) => {
				this.hasLoadError = true; //display error note in the DOM
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
			this.currentSearchString = '';
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
            gsap.set(element, { opacity: 0, y: 10, height: 0 });
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
        onLeaveResult (element, done) {
            gsap.to(element, 0.3, { opacity: 0, y: 10, height: 0, onComplete: done } );
        },

    }

}

</script>

<template>
    <div>
        <input 
            v-model="currentSearchString"
            :readonly="!isLoaded" 
            :placeholder="searchInputPlaceholder"
            type="text"
            class="" 
        />
        <button v-show="currentSearchString.length != 0" @click.prevent="onClearInputSelected" class="">Clear Search</button>
        <transition-group
            name="staggered-fade"
            tag="ul"
            v-bind:css="false"
            @before-enter="onBeforeOnEnter"
            @enter="onEnter"
            @leave="onLeaveResult"
        >
            <li v-for="(result, index) in filteredSearchListLimited" :key="result.name" :data-index="index">
                <admin-settings-search-result 
                    :result="result"
                    :current-search-string="currentSearchString"
                />
            </li>
        </transition-group>
        <div v-if="isSearchActive && hasResultsBeydondSearchResultsShownLimit" class="search-refine-note">{{ resultsBeydondSearchResultsShownLimitCount }} search results not shown, try refining your search further.</div>
    </div>
</template>

<style lang="scss" scoped>

ul {
    margin: 0px;
    padding: 0px;
}

ul li {
    list-style-type: none;
}

.search-refine-note {
    width: 100%;
    border: 1px solid black;
    margin-top: -1px; //so don't get double borders when stacked
    padding: 10px;
    background: lightsteelblue;
    font-style: italic;
}

</style>
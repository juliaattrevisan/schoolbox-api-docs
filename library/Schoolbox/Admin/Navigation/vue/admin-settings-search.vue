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
                return 'Search admin settings...';
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
				if (setting.title?.toLowerCase().includes(this.currentSearchString.toLowerCase())
					|| setting.name?.toLowerCase().includes(this.currentSearchString.toLowerCase())
					|| setting.description?.toLowerCase().includes(this.currentSearchString.toLowerCase())) {
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
    <div class="admin-search-container">
        <input
            v-model="currentSearchString"
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
                @leave="onLeaveResult"
            >
                <li v-for="(result, index) in filteredSearchListLimited" :key="result.name" :data-index="index">
                    <admin-settings-search-result
                        :result="result"
                        :current-search-string="currentSearchString"
                    />
                </li>
            </transition-group>
            <aside v-if="isSearchActive && hasResultsBeydondSearchResultsShownLimit" class="search-refine-note">{{ resultsBeydondSearchResultsShownLimitCount }} search results not shown, try refining your search further.</aside>
        </div>
    </div>
</template>

<style lang="scss" scoped>

.admin-search-container {
  position: relative;

  > div {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    position: absolute;
    top: 41px;
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
    transform: none !important;
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

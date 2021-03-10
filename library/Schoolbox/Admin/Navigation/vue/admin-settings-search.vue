<script>

import AdminSettingsSearchResult from './admin-settings-search-result';
import { fetchAsJson } from '@shared/fetch/fetch';

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
			if ((this.currentSearchString.length > this.minSearchStringLength) && (this.filteredSearchList.length > 0)) {
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
				if (setting.name.toLowerCase().includes(this.currentSearchString.toLowerCase())
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
            if (this.filteredSearchList.length > this.searchResultsShownLimit) { 
                return this.filteredSearchList.filter((setting, index) => index < this.searchResultsShownLimit);
            } else {
                return this.filteredSearchList;
            }

		},

        /**
         * to show the search refine note
         */
        hasResultsBeydondsearchResultsShownLimit () {
			if (this.filteredSearchList.length > this.searchResultsShownLimit) { 
				return true
			} else {
                return false;
            }
		},

        /**
         * to show how many aren't appearing in the search 
         */
        resultsBeydondsearchResultsShownLimitCount () {
			return this.filteredSearchList.length - this.searchResultsShownLimit;
		} 

	},

    mounted () {
        // this.loadSettings();
        this.addBeers();
    },

    methods: {

        addBeers () {
			fetch("https://api.punkapi.com/v2/beers?per_page=80")
			.then((response) => response.json())
			.then((response) => {
				this.settingsList = response;
                this.isLoaded = true;
      		});
		},

        /**
         * load the settings for possible retrieval
         *
         */
		// loadSettings () {
		// 	fetchAsJson('/adminv2/setting/search?keyword=news')
		// 	.then((response) => {
		// 		this.settingsList = response;
		// 		this.isLoaded = true;
        //         debugger;
		// 	}).catch((error) => {
		// 		this.hasLoadError = true; //display error note in the DOM
		// 	});
		// },

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
		}

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
        <transition-group name="fade">
            <template v-if="isSearchActive">
                <div v-for="(result, index) in filteredSearchListLimited" :key="index">
                    <admin-settings-search-result 
                        :result="result"
                        :current-search-string="currentSearchString"
                    />
                </div>
            </template>
        </transition-group>
        <div v-if="isSearchActive && hasResultsBeydondsearchResultsShownLimit">{{ resultsBeydondsearchResultsShownLimitCount }} search results not shown, try refining your search further.</div>
    </div>
</template>

<style lang="scss" scoped>

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s;
}
.fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
  opacity: 0;
}

</style>
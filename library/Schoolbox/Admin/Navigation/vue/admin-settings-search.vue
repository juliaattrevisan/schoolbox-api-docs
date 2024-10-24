<script lang="ts">
/**
 * To help users find admin settings faster (hopefully!)
 * Search field is used to filter the results, then the relevant
 * search result can be selected to move to the relevant page/setting
 */
import { gsap } from "gsap/all";
import ScrollFadeWrapper from "@schoolboxVue/components/scroll-fade-wrapper.vue";
import AdminSettingsSearchResult from "./admin-settings-search-result.vue";

const SB_SETTINGS_LIST = "sb_settingsList";

interface SBSettingsListItem {
    title: string;
    name: string;
    description: string;
    module?: string;
    url: string;
}

export default window.Vue.defineComponent({
    components: {
        AdminSettingsSearchResult,
        ScrollFadeWrapper,
    },

    data() {
        return {
            settingsList: [],
            isLoaded: false,
            hasLoadError: false,
            query: "",
            minQueryLength: 3,
            resultsShownLimit: 6,
            activeIndex: 0, // to track which index is selected
        } as {
            settingsList: SBSettingsListItem[];
            isLoaded: boolean;
            hasLoadError: boolean;
            query: string;
            minQueryLength: number;
            resultsShownLimit: number;
            activeIndex: number;
        };
    },

    computed: {
        /**
         * to notify whether still loading the results or not
         * @returns loading status
         */
        inputPlaceholder(): string {
            if (this.isLoaded) {
                return "Search administration settings...";
            }

            return "Loading settings, please wait...";
        },

        /**
         * only when started typing beyond minQueryLength count and there's actual results
         * @returns start displaying results or not
         */
        isActive(): boolean {
            return this.query.length >= this.minQueryLength && this.resultsList.length > 0;
        },

        /**
         * the search string broken into words, to search for each word
         * @returns the search string broken into single words
         */
        queryWordList(): string[] {
            const allWords = this.query.match(/\b(\w+)\b/g);
            if (allWords && allWords.length > 0) {
                const onlyWordsAboveTwoLetters = allWords.filter((word) => word.length > 1);
                return onlyWordsAboveTwoLetters;
            }
            return [];
        },

        /**
         * the settings relevant to the search input
         * @returns the list of search results
         */
        resultsList(): SBSettingsListItem[] {
            if (this.query.length < this.minQueryLength) {
                return [];
            }

            const matchedTitleList = this.settingsList.filter(this.searchForTitle.bind(this));
            const matchedNameAndDescriptionList = this.settingsList.filter(this.searchForNameAndDescription.bind(this));
            return matchedTitleList.concat(matchedNameAndDescriptionList); // so those that have title matches take precedence
        },
    },

    watch: {
        /**
         * reset the active index when the list changes as the index may become out of range
         * @param newValue - the new list of results
         * @param oldValue - the old list of results
         */
        resultsList(newValue: SBSettingsListItem[], oldValue: SBSettingsListItem[]) {
            if (newValue.length !== oldValue.length && this.activeIndex > 0) {
                this.activeIndex = 0;
            }
        },
    },

    mounted() {
        this.initKeyboardEvents();
        this.initMouseEvents();
        void this.loadSettings();
    },

    methods: {
        /**
         * find all those results that match EACH search word
         * within the the title field
         * @param setting - the setting to check
         * @returns true if found
         */
        searchForTitle(setting: SBSettingsListItem): boolean {
            let wordMatchCount = 0;
            this.queryWordList.forEach((wordInSearch) => {
                if (setting.title?.toLowerCase().includes(wordInSearch.toLowerCase())) {
                    wordMatchCount += 1;
                }
            });
            if (wordMatchCount === this.queryWordList.length) {
                // all words have to match
                return true;
            }
            return false;
        },

        /**
         * find all those results that match EACH search word
         * within the name and description fields
         * @param setting - the setting to check
         * @returns true if found
         */
        searchForNameAndDescription(setting: SBSettingsListItem): boolean {
            let wordMatchCount = 0;
            this.queryWordList.forEach((wordInSearch) => {
                if (
                    (setting.name?.toLowerCase().includes(wordInSearch.toLowerCase()) ||
                        setting.description?.toLowerCase().includes(wordInSearch.toLowerCase())) &&
                    !setting.title?.toLowerCase().includes(wordInSearch.toLowerCase())
                ) {
                    wordMatchCount += 1;
                }
            });
            if (wordMatchCount === this.queryWordList.length) {
                // all words have to match
                return true;
            }
            return false;
        },

        /**
         * to be able to select by enter and navigate by up/down arrows
         */
        initKeyboardEvents() {
            document.addEventListener("keyup", (event) => {
                const keyName = event.key;
                const keyOptions: Record<string, () => void> = {
                    Enter: () => this.onEnterKeyPressed(),
                    ArrowUp: () => this.decrementActiveIndex(),
                    ArrowDown: () => this.incrementActiveIndex(),
                };
                if (keyOptions[keyName] && document.activeElement === this.$refs.queryInput) {
                    // ensure search input has focus
                    keyOptions[keyName]();
                }
            });
        },

        /**
         * an alternative to mouse press,
         * select the currently active search result
         */
        onEnterKeyPressed() {
            if (this.resultsList.length > 0 && this.activeIndex !== -1) {
                // only if there's results to select and activeIndex is set
                this.selectResult(this.resultsList[this.activeIndex].url);
            }
        },

        /**
         * to be able to move up and down through the selections
         * with the arrow keys
         */
        decrementActiveIndex() {
            if (this.activeIndex > 0) {
                this.activeIndex -= 1;
                this.scrollActiveResultIntoView();
            }
        },

        /**
         * to be able to move up and down through the selections
         * with the arrow keys
         */
        incrementActiveIndex() {
            if (this.activeIndex < this.resultsList.length - 1) {
                this.activeIndex += 1;
                this.scrollActiveResultIntoView();
            }
        },

        /**
         * Scroll the active search result into view
         */
        scrollActiveResultIntoView() {
            if (this.activeIndex !== -1) {
                const activeElement = (this.$refs.searchResultListItems as HTMLElement[])[this.activeIndex];
                activeElement.scrollIntoView({ block: "nearest", behavior: "smooth" });
            }
        },

        /**
         * to be able to close the menu when something else
         * is selected outside the menu
         */
        initMouseEvents() {
            document.addEventListener("click", (event) => {
                if (event.target instanceof Node && !(this.$el as HTMLElement).contains(event.target)) {
                    this.clearInput();
                }
            });
        },

        /**
         * check if already loaded and saved, or load up
         */
        async loadSettings() {
            if (sessionStorage.getItem(`${SB_SETTINGS_LIST}-${window.schoolboxUser.id}`)) {
                this.loadSessionSettings();
            } else {
                await this.loadRemoteSettings();
            }
        },

        /**
         * pull the settings from local storage
         */
        loadSessionSettings() {
            const sessionStorageSettingsList = sessionStorage.getItem(SB_SETTINGS_LIST);

            if (sessionStorageSettingsList === null) {
                this.settingsList = [];
            } else {
                this.settingsList = JSON.parse(sessionStorageSettingsList) as SBSettingsListItem[]; // convert the saved string var back to JSON
            }
            this.isLoaded = true;
        },

        /**
         * load the settings for possible retrieval
         */
        async loadRemoteSettings() {
            try {
                const response = await fetch("/adminv2/setting/search");
                const responseJson = (await response.json()) as SBSettingsListItem[];
                this.settingsList = responseJson;
                sessionStorage.setItem(
                    // Index by userId so that for example if a superuser logs out and then a non-superuser moderator
                    // logs in using the same browser tab, the moderator will not see the superuser's search index.
                    `${SB_SETTINGS_LIST}-${window.schoolboxUser.id}`,
                    JSON.stringify(this.settingsList),
                ); // session storage vars need to be saved as strings only
            } catch (error) {
                this.hasLoadError = true; // hide search bar in the dom
            } finally {
                this.isLoaded = true;
            }
        },

        /**
         * clear the search button selected
         */
        onClearInputSelected() {
            this.clearInput();
        },

        /**
         * clear the search
         */
        clearInput() {
            this.query = "";
            this.activeIndex = -1;
        },

        /**
         * how the element animates off
         */
        checkEachResultIndex() {
            (this.$refs.searchResults as InstanceType<typeof AdminSettingsSearchResult>[]).forEach((searchResult) => {
                searchResult.checkIndex();
            });
        },

        /**
         * animate settings to help context when filtering
         * refer to https://vuejs.org/v2/guide/transitions.html#Staggering-List-Transitions
         * what happens before shown
         * @param element - the element that's animating
         */
        onBeforeOnEnter(element: Element) {
            gsap.set(element, { opacity: 0, y: 10, height: 0 });
        },

        /**
         * how it element animates on
         * @param element - the element that's animating
         * @param done - to be called once animation is complete
         */
        onEnter(element: Element, done: () => void) {
            const elementIndex = element.getAttribute("index") ?? "0";
            const delay = parseInt(elementIndex, 10) * 0.025;
            gsap.to(element, 0.3, { opacity: 1, y: 0, height: "auto", delay, onComplete: done });
        },

        /**
         * how the element animates off
         * @param element - the element that's animating
         * @param done - to be called once animation is complete
         */
        onLeave(element: Element, done: () => void) {
            gsap.to(element, 0.3, { opacity: 0, y: 10, height: 0, onComplete: done });
        },

        /**
         * a result has been moused over via #admin-settings-search-result
         */
        onMouseOverResult() {
            this.activeIndex = -1; // disable the keyboard selection, mouse takes precedence until search is updated
        },

        /**
         * a result has been selected via #admin-settings-search-result
         * @param url - the url to move to
         */
        onSelectedResult(url: string) {
            this.selectResult(url);
        },

        /**
         * to move to the page the selected search result is on
         * which will have the result highlighted
         * @param url - the url to move to
         */
        selectResult(url: string) {
            this.clearInput();
            window.location.href = url;
            if (window.location.href !== url) {
                // only dispatch if on the same page, otherwise handled in page load in #moduleConfig.twig
                const event = new CustomEvent("admin-settings-search__on-result-selected", {
                    detail: {
                        url,
                    },
                });
                document.dispatchEvent(event);
            }
        },
    },
});
</script>

<template>
    <div class="admin-search-container">
        <input
            v-if="!hasLoadError"
            ref="queryInput"
            v-model="query"
            :readonly="!isLoaded"
            :placeholder="inputPlaceholder"
            type="text"
            @input="query = ($event.target as HTMLInputElement).value /* Fix Android events SA-12170 */"
        />
        <scroll-fade-wrapper wrapper-tag="div" slotted-element-selector="ul">
            <transition-group
                name="staggered-fade"
                tag="ul"
                :css="false"
                @before-enter="onBeforeOnEnter"
                @enter="onEnter"
                @enter-cancelled="checkEachResultIndex"
                @leave="onLeave"
                @after-leave="checkEachResultIndex"
                @leave-cancelled="checkEachResultIndex"
            >
                <li
                    v-for="(result, index) in resultsList"
                    :key="result.name"
                    ref="searchResultListItems"
                    :data-index="index"
                >
                    <admin-settings-search-result
                        ref="searchResults"
                        :result="result"
                        :query="query"
                        :active-index="activeIndex"
                        @on-mouse-over-result="onMouseOverResult"
                        @on-selected-result="onSelectedResult"
                    />
                </li>
            </transition-group>
        </scroll-fade-wrapper>
    </div>
</template>

<style lang="scss" scoped>
@import "@css/core/base/_variables.scss";

.admin-search-container {
    position: relative;
    > div {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        position: absolute;
        top: 40px;
        z-index: var(--sbx-depth-pop-over);
    }
}

ul {
    list-style: none;
    margin: 0;
    max-height: 400px;
    overflow-y: auto;
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
</style>

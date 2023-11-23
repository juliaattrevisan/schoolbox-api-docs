<script setup lang="ts">
/**
 * The search bar that currently appears within the top menu.
 * It is currently used to search for users and prompt a full search,
 * providing the option to be redirected to the search page.
 */
import debounce from "lodash/debounce";
import { vOnClickOutside } from "@vueuse/components";
import SearchInput from "@schoolboxVue/components/search/search-input.vue";

interface Result {
    id: string;
    formattedName: string;
    metadata: string;
}

interface AllResults {
    totalCount: number;
    results: Result[];
}

const isFocused = window.Vue.ref(false);
const isActive = window.Vue.ref(false); // If the search input is active, to add the active styles
const keyword = window.Vue.ref("");
const results = window.Vue.ref<Result[]>([]);
const isOpen = window.Vue.ref(false);
const isLoading = window.Vue.ref(true);
const hasLoadError = window.Vue.ref(false);
const totalCount = window.Vue.ref(0);
const arrowCounter = window.Vue.ref(-1); // Want to automatically select the first result? set this to 0
const formElement = window.Vue.ref<HTMLFormElement | null>(null);
const minKeywordLength = 2;
const paths = {
    portrait: "/portrait.php?id=",
    searchEndpoint: "/user/search?keyword=",
    userPage: "/search/user/",
    searchPage: "/search?keyword=",
};

/**
 * Input added
 * @param {string} value - The input value
 */
function onInput(value: string) {
    keyword.value = value;
}

/**
 * Arrow pressed down, moving to next result
 */
function onArrowDown() {
    if (arrowCounter.value < results.value.length - 1) {
        arrowCounter.value += 1;
    }
}

/**
 * Arrow pressed up, moving to previous result
 */
function onArrowUp() {
    if (arrowCounter.value > -1) {
        arrowCounter.value -= 1;
    }
}

/**
 * Submit the current query, using _debounce to accommodate for key presses
 * so not sending on every key press
 */
const filterResults = debounce(() => {
    fetch(`${paths.searchEndpoint}${keyword.value}`)
        .then((response) => response.json())
        .then((data: AllResults) => {
            totalCount.value = data.totalCount;
            results.value = data.results;
        })
        .finally(() => {
            isLoading.value = false;
        })
        .catch(() => {
            hasLoadError.value = true;
        });
}, 500);

/**
 * Only start searching once the keyword length is greater than the minimum
 */
window.Vue.watch(keyword, () => {
    if (keyword.value.length >= minKeywordLength) {
        isOpen.value = true;
        isLoading.value = true;
        results.value = [];
        filterResults();
    }
});

/**
 * Navigate to the chosen result or submit the form to go to the search page
 */
function onEnter() {
    if (arrowCounter.value > 0) {
        window.location.href = `${paths.userPage}${results.value[arrowCounter.value].id}`;
    } else {
        (formElement.value as HTMLFormElement).submit();
    }
}

/**
 * Submit the form, heading to the search page
 */
function onClicked() {
    if (keyword.value.length === 0) {
        window.location.href = "/search";
    } else {
        (formElement.value as HTMLFormElement).submit();
    }
}

/**
 * Only close the search results if the user clicks outside of the entire search component
 */
function onClickOutside() {
    isFocused.value = false;
    isActive.value = false;
}

/**
 * The search input has gained focus
 */
function onFocusOver() {
    isFocused.value = true;
}

/**
 * The search input has lost focus, however in this scenario,
 * we want to keep any search results open until the user clicks outside
 * of the header search component
 */
function onFocusOut() {
    if (keyword.value.length === 0) {
        isFocused.value = false;
    }
}

/**
 * The user has hovered over the search bar
 */
function onMouseOver() {
    isActive.value = true;
    // If left nav is unpinned and currently open, close it
    const offCanvasWrap = document.getElementById("off-canvas-wrap") as HTMLDivElement;
    if (offCanvasWrap.classList.contains("move-right")) {
        offCanvasWrap.classList.remove("move-right");
    }
    /**
     * The hamburger menu's default z-index is 1001 and to ensure that the icon won't be visible everytime
     * the user does a mouse focus/over in the searchbar, let's convert the z-index to 1000 by adding the
     * search-is-active class
     *
     * Unfortunately, we can't just remove this class especially when the left menu is unpinned.
     * Reason is: If the user does a mouseover in results row, mouseout gets triggered.
     * And if we remove this injected class, when we do a mouseover, the hamburger menu will overlap with
     * the text in the search field.
     *
     * Since we don't have the whole chrome in VueJS yet, for now, we'll need to do this in a not so elegant
     * way as this component doesn't have the ability to communicate with the rest of the chrome elements yet.
     */
    document.querySelector("#menu-toggle")?.classList.add("search-is-active");
}

/**
 * The mouse has moved off the search bar, though we want to keep the search input 'active'
 * if focus is still on the search input
 */
function onMouseOut() {
    if (!isFocused.value) {
        isActive.value = false;
    }
}

/**
 * To display the users portrait image
 * @param {string} id - The user id
 * @returns {string} - The path to the image
 */
function formatPortrait(id: string) {
    return `${paths.portrait}${id}&size=square64`;
}
</script>

<template>
    <form
        id="search"
        ref="formElement"
        v-on-click-outside="onClickOutside"
        class="c-header-search"
        action="/search"
        :class="{ focus: isFocused }"
    >
        <search-input
            :is-active-when-empty="false"
            @input="onInput"
            @key-down="onArrowDown"
            @key-up="onArrowUp"
            @key-enter="onEnter"
            @focus-over="onFocusOver"
            @focus-out="onFocusOut"
            @mouse-over="onMouseOver"
            @mouse-out="onMouseOut"
            @click="onClicked"
        />
        <div v-show="isFocused && isOpen && keyword.length >= minKeywordLength" class="c-header-search__results">
            <div class="l-flex-col">
                <div v-if="results.length && results.length !== totalCount" class="c-card c-card--border-end">
                    <span class="meta">Showing {{ results.length }} of {{ totalCount }} matching users</span>
                </div>
                <a
                    v-for="(result, i) in results"
                    :key="i"
                    :class="`c-card c-card--link c-card--border-end ${i === arrowCounter ? 'c-card--active' : ''}`"
                    :href="paths.userPage + result.id"
                    :data-test="`header-search-user-result-link-${result.formattedName}`"
                    @mouseover="arrowCounter = i"
                    @mouseleave="arrowCounter = -1"
                >
                    <div class="l-flex-row l-flex--align-center l-gap-s l-pad-s">
                        <div class="c-img c-img--s c-img--round">
                            <div class="c-img__wrapper">
                                <img
                                    :src="formatPortrait(result.id)"
                                    :alt="`Portrait photo of ${result.formattedName}`"
                                />
                            </div>
                        </div>
                        <div class="l-flex-col l-flex--justify-center f-no-margins">
                            <h3>
                                {{ result.formattedName }}
                            </h3>
                            <span class="meta">{{ result.metadata }}</span>
                        </div>
                    </div>
                </a>
                <a
                    v-if="results.length && totalCount > results.length"
                    class="c-card c-card--link c-card--active"
                    :href="`${paths.searchPage}${keyword}#user`"
                >
                    <a :href="`${paths.searchPage}${keyword}#user`">View all {{ totalCount }} users</a>
                </a>
                <div v-if="isLoading" class="c-card c-card--loading">
                    <div class="l-flex-row l-gap-s">
                        <div class="c-img c-img--round">
                            <div class="c-img__wrapper">
                                <div class="img-placeholder"></div>
                            </div>
                        </div>
                        <div class="l-flex-col l-flex--justify-center l-flex__item--1">
                            <h3></h3>
                            <span></span>
                        </div>
                    </div>
                </div>
                <template v-if="!isLoading && !results.length">
                    <div class="c-card">
                        <span class="meta">No users were found matching "{{ keyword }}"</span>
                    </div>
                    <a class="c-card c-card--link c-card--active" :href="`${paths.searchPage}${keyword}#all`">
                        <a :href="`${paths.searchPage}${keyword}#all`">Search everything for "{{ keyword }}"</a>
                    </a>
                </template>
            </div>
        </div>
    </form>
</template>

<style lang="scss">
@import "./header-search.scss";
</style>

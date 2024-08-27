<script setup lang="ts">
/**
 * The search bar that currently appears within the top menu.
 * It is currently used to search for users and prompt a full search,
 * providing the option to be redirected to the search page.
 */
import debounce from "lodash/debounce";
import { vOnClickOutside } from "@vueuse/components";
import SearchInput from "@schoolboxVue/components/search/search-input.vue";

interface MetaContext {
    name: string;
    url: string;
}

interface Result {
    id: number;
    title: string;
    meta: string | MetaContext[];
    type: string;
    image: string;
    icon: string;
    url: string;
}

interface AllResults {
    metadata: { totalCountUsers: number | null; totalCountFolders: number | null };
    data: Result[];
}

const isFocused = window.Vue.ref(false);
const isActive = window.Vue.ref(false); // If the search input is active, to add the active styles
const isSelected = window.Vue.ref(false); // If the search input is selected
const keyword = window.Vue.ref("");
const results = window.Vue.ref<Result[]>([]);
const isOpen = window.Vue.ref(false);
const isLoading = window.Vue.ref(true);
const hasLoadError = window.Vue.ref(false);
const totalCountFolders = window.Vue.ref(0);
const totalCountUsers = window.Vue.ref(0);
const arrowCounter = window.Vue.ref(-1); // Want to automatically select the first result? set this to 0
const formElement = window.Vue.ref<HTMLFormElement | null>(null);
const minKeywordLength = 2;
const paths = {
    portrait: "/portrait.php?id=",
    searchEndpoint: "/search/header?keyword=",
    userPage: "/search/user/",
    searchPage: "/search",
    searchPageWithKeyword: "/search?keyword=",
    usersFilter: `filter=%7B"date"%3A%7B%7D%2C"creator"%3A%7B%7D%2C"types"%3A%5B"user"%2C"user.guest"%2C"user.parent"%2C"user.staff"%2C"user.student"%5D%7D`,
};

/**
 * Only the user results
 * @returns {Result[]} - the user results
 */
const userResults = window.Vue.computed(() => {
    return results.value.filter((res) => res.type.includes("user"));
});

/**
 * Only the page results
 * @returns {Result[]} - the page results
 */
const pageResults = window.Vue.computed(() => {
    return results.value.filter((res) => res.type.includes("page"));
});

/**
 * The results combined, so we can still loop through key up / key down and assign relevant focus on item over
 * @returns {Result[]} - all results
 */
const allResults = window.Vue.computed(() => {
    return [...userResults.value, ...pageResults.value];
});

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
    if (arrowCounter.value < allResults.value.length - 1) {
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
            totalCountUsers.value = data.metadata.totalCountUsers ?? 0;
            totalCountFolders.value = data.metadata.totalCountFolders ?? 0;
            results.value = data.data;
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
    if (arrowCounter.value >= 0) {
        window.location.href = allResults.value[arrowCounter.value].url;
    } else {
        (formElement.value as HTMLFormElement).submit();
    }
}

/**
 * Submit the form, heading to the search page
 */
function onClicked() {
    if (keyword.value.length === 0) {
        window.location.href = paths.searchPage;
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
    isSelected.value = false;
}

/**
 * The search input has gained focus
 */
function onFocusOver() {
    isFocused.value = true;
    isSelected.value = true;
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
     * The hamburger menu's default z-index is 1001 and to ensure that the icon won't be visible every time
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
 * The search button href, if the keyword is empty, go to the search page
 * @returns {string} - The href
 */
const searchButtonHref = window.Vue.computed(() => {
    if (keyword.value.length === 0) {
        return paths.searchPage;
    }
    return `${paths.searchPageWithKeyword}${keyword.value}`;
});

/**
 * The search button text, if the keyword is empty, display 'Advanced Search'
 * @returns {string} - The text
 */
const searchButtonText = window.Vue.computed(() => {
    if (keyword.value.length === 0) {
        return "Advanced Search";
    }
    return `Search everything for "${keyword.value}"`;
});
</script>

<template>
    <form
        id="search"
        ref="formElement"
        v-on-click-outside="onClickOutside"
        class="c-header-search"
        action="/search"
        :class="{ focus: isSelected }"
    >
        <search-input
            :is-active-when-empty="false"
            :is-selected="isSelected"
            @input="onInput"
            @keydown.arrow-down="onArrowDown"
            @keydown.arrow-up="onArrowUp"
            @key-enter="onEnter"
            @focus-over="onFocusOver"
            @focus-out="onFocusOut"
            @mouse-over="onMouseOver"
            @mouse-out="onMouseOut"
            @click="onClicked"
        />
        <div v-show="isSelected" class="c-header-search__results">
            <div v-if="isOpen && keyword.length >= minKeywordLength" class="l-flex-col">
                <div
                    v-if="userResults.length && userResults.length !== totalCountUsers"
                    class="c-card c-card--border-end l-pad-s"
                >
                    <em>Showing {{ userResults.length }} of {{ totalCountUsers }} matching users</em>
                </div>
                <div
                    v-else-if="userResults.length && totalCountUsers === null"
                    class="c-card c-card--border-end l-pad-s"
                >
                    <em>Showing top {{ userResults.length }} matching users</em>
                </div>
                <a
                    v-for="(result, i) in userResults"
                    :key="i"
                    :class="`c-card c-card--link c-card--border-end ${i === arrowCounter ? 'c-card--active' : ''}`"
                    :href="result.url"
                    :data-test="`header-search-user-result-link-${result.title}`"
                    @mouseover="arrowCounter = i"
                    @mouseleave="arrowCounter = -1"
                >
                    <div class="l-flex-row l-flex--align-center l-flex--no-wrap l-gap-s l-pad-s">
                        <div class="c-img c-img--s c-img--round l-flex__item--0-shrink">
                            <div class="c-img__wrapper">
                                <img :src="result.image" :alt="`Portrait photo of ${result.title}`" />
                            </div>
                        </div>
                        <div class="l-flex-col l-flex--justify-center f-no-margins">
                            <h3>
                                {{ result.title }}
                            </h3>
                            <span class="meta">{{ result.meta }}</span>
                        </div>
                    </div>
                </a>
                <div
                    v-if="pageResults.length && totalCountFolders !== null && pageResults.length !== totalCountFolders"
                    class="c-card c-card--border-end l-pad-s"
                >
                    <em>Showing {{ pageResults.length }} of {{ totalCountFolders }} matching pages</em>
                </div>
                <div
                    v-else-if="pageResults.length && totalCountFolders === null"
                    class="c-card c-card--border-end l-pad-s"
                >
                    <em>Showing top {{ pageResults.length }} matching pages</em>
                </div>
                <a
                    v-for="(result, i) in pageResults"
                    :key="i + userResults.length"
                    :class="`c-card c-card--link c-card--border-end ${
                        i + userResults.length === arrowCounter ? 'c-card--active' : ''
                    }`"
                    :href="result.url"
                    :data-test="`header-search-page-result-link-${result.title}`"
                    @mouseover="arrowCounter = i + userResults.length"
                    @mouseleave="arrowCounter = -1"
                >
                    <div class="l-flex-row l-flex--align-center l-flex--no-wrap l-gap-s l-pad-s">
                        <div class="c-img c-img--s l-flex__item--0-shrink">
                            <div class="c-img__wrapper">
                                <img v-if="result.image" :src="result.image" :alt="`photo of ${result.title}`" />
                                <div v-else :class="`icon icon-${result.icon}`"></div>
                            </div>
                        </div>
                        <div class="l-flex-col l-flex--justify-center f-no-margins">
                            <h3>
                                {{ result.title }}
                            </h3>
                            <span v-if="typeof result.meta !== 'string' && result.meta.length > 0" class="meta">
                                <template v-for="(context, index) in result.meta" :key="context.name">
                                    <a :href="context.url">{{ context.name }}</a>
                                    <template v-if="index < result.meta.length - 1"> > </template>
                                </template>
                            </span>
                        </div>
                    </div>
                </a>
                <div v-if="isLoading" class="c-card c-card--loading">
                    <div class="l-flex-row l-gap-s l-pad-s">
                        <div class="c-img c-img--s c-img--round">
                            <div class="c-img__wrapper">
                                <div class="img-placeholder"></div>
                            </div>
                        </div>
                        <div class="l-flex-col l-flex--justify-center l-flex__item--1 l-gap-s">
                            <h3></h3>
                            <span></span>
                        </div>
                    </div>
                </div>
                <div v-if="!isLoading && !userResults.length && !pageResults.length" class="c-card l-pad-s">
                    <em>No users or pages were found matching "{{ keyword }}"</em>
                </div>
            </div>
            <div class="l-flex-col">
                <a class="button f-no-margins t-lh--normal icon-search" :href="searchButtonHref">
                    {{ searchButtonText }}
                </a>
            </div>
        </div>
    </form>
</template>

<style lang="scss">
@import "./header-search.scss";
</style>

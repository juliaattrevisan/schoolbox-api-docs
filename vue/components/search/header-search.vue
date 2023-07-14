<script setup lang="ts">
import debounce from "lodash/debounce";
import SearchInput from "./search-input.vue";
import HeaderSearchResultMeta from "./header-search-result-meta.vue";

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
 * Submit the current query, using underscore.js _debounce to accommodate for key presses
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

window.Vue.watch(keyword, () => {
    if (keyword.value.length >= minKeywordLength) {
        isOpen.value = true;
        isLoading.value = true;
        results.value = [];
        filterResults();
    }
});

/**
 * To a new browser page
 * @param {string} path - The path to go to
 */
function goToPath(path: string) {
    window.location.href = path;
}

/**
 * Navigate to the chosen result
 */
function onEnter() {
    if (arrowCounter.value > 0) {
        goToPath(`${paths.userPage}${results.value[arrowCounter.value].id}`);
    } else {
        (formElement.value as HTMLFormElement).submit();
    }
}

/**
 * Submit the form, heading to the search page
 */
function onClicked() {
    (formElement.value as HTMLFormElement).submit();
}

/**
 * Display the search results
 * @param { Event } event - The event object
 */
function onFocusOver(event: Event) {
    if (event.type === "focus") {
        const offCanvasWrap = document.getElementById("off-canvas-wrap") as HTMLDivElement;
        // If left nav is unpinned and currently open, close it
        if (offCanvasWrap.classList.contains("move-right")) {
            offCanvasWrap.classList.remove("move-right");
        }
        isFocused.value = true;
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
 * To display the users portrait image
 * @param {string} id - The user id
 * @returns {string} - The path to the image
 */
function formatPortrait(id: string) {
    return `${paths.portrait}${id}&size=square64`;
}
</script>

<template>
    <form id="search" ref="formElement" class="global-search" action="/search" :class="{ focus: isFocused }">
        <search-input
            @input="onInput"
            @key-down="onArrowDown"
            @key-up="onArrowUp"
            @key-enter="onEnter"
            @focus-over="onFocusOver"
            @focus-out="isFocused = false"
            @click="onClicked"
        />
        <ul v-show="isFocused && isOpen && keyword.length >= minKeywordLength" class="results information-list">
            <li v-if="results.length && results.length !== totalCount" class="result-meta" @click.prevent>
                <header-search-result-meta
                    >Showing {{ results.length }} of {{ totalCount }} matching users</header-search-result-meta
                >
            </li>
            <li v-for="(result, i) in results" :key="i" class="result">
                <a
                    class="link-row"
                    :class="{ 'is-active': i === arrowCounter }"
                    @click="goToPath(paths.userPage + result.id)"
                    @mousedown.left="goToPath(paths.userPage + result.id)"
                    @mouseover="arrowCounter = i"
                    @mouseleave="arrowCounter = -1"
                >
                    <div class="card small-12">
                        <img :src="formatPortrait(result.id)" alt="portrait photo" />
                        <h3 class="name">{{ result.formattedName }}</h3>
                        <p class="meta">{{ result.metadata }}</p>
                    </div>
                </a>
            </li>
            <li v-if="isLoading" class="result-meta">
                <header-search-result-meta><div class="loader"></div></header-search-result-meta>
            </li>
            <li
                v-if="results.length && totalCount > results.length"
                class="result-meta with-bg"
                @mousedown.left="goToPath(paths.searchPage + keyword + '#user')"
            >
                <header-search-result-meta> View all {{ totalCount }} users </header-search-result-meta>
            </li>
            <li v-if="!isLoading && !results.length" class="result-meta">
                <header-search-result-meta>No users were found matching "{{ keyword }}"</header-search-result-meta>
            </li>
            <li
                v-if="!isLoading && !results.length"
                class="result-meta with-bg"
                @click="goToPath(paths.searchPage + keyword + '#all')"
                @mousedown.left="goToPath(paths.searchPage + keyword + '#all')"
            >
                <header-search-result-meta> Search everything for "{{ keyword }}" </header-search-result-meta>
            </li>
        </ul>
    </form>
</template>

<style scoped>
.inner-wrap:not(.left-open):not(.right-open) .global-search:hover,
.inner-wrap:not(.left-open):not(.right-open) .global-search.focus {
    padding-left: 9rem;
}
</style>

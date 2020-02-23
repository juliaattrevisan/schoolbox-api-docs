<template>
    <form id="search" class="global-search" action="/search" v-bind:class="{focus: isFocused}">
        <input id="search-field"
               type="text"
               name="keyword"
               placeholder="Search"
               autocomplete="off"
               v-model.trim="keyword"
               @input="onChange"
               @keydown.down="onArrowDown"
               @keydown.up="onArrowUp"
               @keydown.enter.prevent="onEnter"
               @focus="onFocusOver"
               @mouseover="onFocusOver"
               v-click-outside="lostFocus"
               @blur="isFocused=false"
               @focusout="isFocused=false"
               maxlength="2048"
        />
        <ul class="results information-list" v-show="isFocused && isOpen">
            <li class="result-meta" v-if="results.length && results.length !== totalCount" @click.prevent>
                <result-meta>Showing {{ results.length }} of {{ totalCount }} matching users</result-meta>
            </li>
            <li v-for="(result, i) in results" :key="i" class="result">
                <a class="link-row" @click="goToPath(paths.userPage + result.id)" @mousedown.left="goToPath(paths.userPage + result.id)" v-bind:class="{ 'is-active': i === arrowCounter }" @mouseover="arrowCounter = i" @mouseleave="arrowCounter = -1">
                    <div class="card small-12">
                        <img v-bind:src="formatPortrait(result.id)">
                        <h3 class="name">{{ result.formattedName }}</h3>
                        <p class="meta">{{ result.metadata }}</p>
                    </div>
                </a>
            </li>
            <li class="result-meta" v-if="isLoading">
                <result-meta><div class="loader"></div></result-meta>
            </li>
            <li class="result-meta with-bg" v-if="results.length && totalCount > results.length" @mousedown.left="goToPath(paths.searchPage + keyword + '#user')">
                <result-meta>
                    View all {{ totalCount }} users
                </result-meta>
            </li>
            <li class="result-meta" v-if="!isLoading && !results.length">
                <result-meta>No users were found matching "{{ keyword }}"</result-meta>
            </li>
            <li class="result-meta with-bg" v-if="!isLoading && !results.length" @click="goToPath(paths.searchPage + keyword + '#all')" @mousedown.left="goToPath(paths.searchPage + keyword + '#all')">
                <result-meta>
                    Search everything for "{{ keyword }}"
                </result-meta>
            </li>
        </ul>
        <label data-jsubmit for="search-field" class="icon-search no-text"></label>
    </form>
</template>

<script>
    import ResultMeta from "./slots/result-meta";

    export default {
        components: {ResultMeta},

        data: function () {
            return {
                isFocused: false,
                keyword: '',
                results: [],
                isOpen: false,
                isLoading: true,
                minKeywordLength: 2,
                totalCount: 0,
                arrowCounter: -1, // Want to automatically select the first result? set this to 0
                paths: {
                    'portrait': '/portrait.php?id=',
                    'searchEndpoint': '/user/search?keyword=',
                    'userPage': '/search/user/',
                    'searchPage': '/search?keyword=',
                }
            }
        },

        methods: {

            onChange:  _.debounce(function (e) {
                var self = this;

                if (self.keyword.length >= self.minKeywordLength) {
                    self.isOpen = true;
                    self.isLoading = true;
                    self.results = [];
                    self.filterResults();
                }
            }, 500),

            onArrowDown: function() {
                if (this.arrowCounter < this.results.length - 1) {
                    this.arrowCounter = this.arrowCounter + 1;
                }
            },

            onArrowUp: function() {
                if (this.arrowCounter > -1) {
                    this.arrowCounter = this.arrowCounter - 1;
                }
            },

            onEnter: function(id) {
                if (this.arrowCounter > 0) {
                    this.goToPath(this.paths.userPage + this.results[this.arrowCounter].id);
                } else {
                    document.getElementById('search').submit();
                }
            },

            lostFocus: function(event) {
                this.isFocused = false;
                this.arrowCounter = -1; // Reset arrow pointer to none

                /**
                 * Revert back the z-index value back to the default value of 1001
                 */
                document.querySelector('#menu-toggle').classList.remove('search-is-active');
            },

            onFocusOver: function(event) {

                if (event.type === 'focus') {
                    var offCanvasWrap = document.getElementById('off-canvas-wrap');

                    // If left nav is unpinned and currently open, close it
                    if (offCanvasWrap.classList.contains('move-right')) {
                        offCanvasWrap.classList.remove('move-right');
                    }

                    this.isFocused = true;
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
                 *
                 * TODO: Convert the whole chrome to VueJS to prevent/minimise this kinds of implementation
                 */
                document.querySelector('#menu-toggle').classList.add('search-is-active');
            },

            goToPath: function(path) {
                window.location.href = path;
            },

            filterResults: function() {
                var self = this;

                axios.get(self.paths.searchEndpoint + self.keyword).then(function (response) {
                    self.totalCount = response.data.totalCount;
                    self.results = response.data.results;
                }).finally(function () {
                    self.isLoading = false;
                }).catch(function (error) {
                    console.log(error);
                });
            },

            formatPortrait: function(id) {
                return this.paths.portrait + id + '&size=square64';
            }
        }
    }
</script>

<style scoped>
    .inner-wrap:not(.left-open):not(.right-open) .global-search:hover,
    .inner-wrap:not(.left-open):not(.right-open) .global-search.focus {
        padding-left: 9rem;
    }
</style>

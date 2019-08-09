<template>
    <form id="search" class="global-search" action="/search" v-bind:class="{focus: isFocused}">
        <input id="search-field"
               type="text"
               name="keyword"
               placeholder="Search"
               autocomplete="off"
               v-model.trim="keyword"
               @focus="onFocusOrOver"
               @input="onChange"
               @keydown.down="onArrowDown"
               @keydown.up="onArrowUp"
               @keydown.enter.prevent="onEnter"
               v-click-outside="lostFocus"
               @blur="isFocused=false"
               @focusout="isFocused=false"
               @mouseover="onFocusOrOver"
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
                        <p class="meta">{{ formatMeta(result) }}</p>
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

            // Method gets called when the user clicked outside this component e.g: clicking in the profile icon and etc.
            lostFocus: function(event) {
                this.isFocused = false;
                this.arrowCounter = -1; // Reset arrow pointer to none

                // Remove z-index that was injected
                document.querySelector('.left-small').removeAttribute('style');
            },

            // This method gets called when the user clicked or do a mouseover this component
            onFocusOrOver: function(event) {

                if (event.type === 'focus') {
                    var offCanvasWrap = document.getElementById('off-canvas-wrap');

                    // If left nav is unpinned and currently open, close it
                    if (offCanvasWrap.classList.contains('move-right')) {
                        offCanvasWrap.classList.remove('move-right');
                    }

                    this.isFocused = true;
                }

                // Unfortunately, we'll need to do this when the nav is unpinned
                document.querySelector('.left-small').style.zIndex = 1000;
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

            formatMeta: function(result) {
                if (result.year) {
                    return 'Year ' + result.year + ' ' + result.metadata;
                }

                return result.metadata;
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

<template>
    <form id="search" class="global-search" action="/search" v-bind:class="{focus: isFocused}">
        <input id="search-field"
               type="text"
               name="keyword"
               placeholder="Search"
               autocomplete="off"
               v-model.trim="keyword"

               @focus="isFocused=true"

               @input="onChange"
               @keydown.down="onArrowDown"
               @keydown.up="onArrowUp"
               @keydown.enter.prevent="onEnter"
               v-click-outside="lostFocus"
        />
        <ul class="results information-list" v-show="isFocused && isOpen">
            <li class="result-meta" v-if="results.length && results.length !== totalCount" @click.prevent>
                <result-meta>Showing {{ results.length }} of {{ totalCount }} matching users</result-meta>
            </li>
            <li v-for="(result, i) in results" :key="i" class="result">
                <a class="link-row" @mousedown.left="goToPath(paths.userPage + result.id)" v-bind:class="{ 'is-active': i === arrowCounter }" @mouseover="arrowCounter = i" @mouseleave="arrowCounter = -1">
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
            <li class="result-meta with-bg" v-if="!isLoading && !results.length" @mousedown.left="goToPath(paths.searchPage + keyword + '#all')">
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
                minKeywordLenght: 3,
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

                if (self.keyword.length >= self.minKeywordLenght) {
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
                this.arrowCounter = -1;
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

<template>
    <form id="search" class="global-search" action="/search" v-bind:class="{focus: isFocused}">
        <input id="search-field"
               type="text"
               name="keyword"
               placeholder="Search"
               autocomplete="off"
               v-model.trim="keyword"
               @mousedown="isFocused=true"
               @blur="isFocused=false"
               @keydown.down="onArrowDown"
               @keydown.up="onArrowUp"
               @keydown.ctrl.enter="onEnter"
               @input="onChange"
               v-click-outside="lostFocus"
        />
        <ul class="results" v-show="isFocused && isOpen">
            <li class="result-meta" v-if="results.length && results.length !== totalCount">
                <result-meta>Showing {{ results.length }} of {{ totalCount }} matching users</result-meta>
            </li>
            <li v-for="(result, i) in results" :key="i" class="result actions-small-1">
                <div class="list-item small-12" v-bind:class="{ 'is-active': i === arrowCounter }">
                    <a @mousedown="goToProfilePage(result.id)">
                        <img v-bind:src="formatPortrait(result.id)">
                        <p class="name">{{ result.formattedName }}</p>
                        <p class="meta">{{ formatMeta(result) }}</p>
                    </a>
                </div>
            </li>
            <li class="result-meta" v-if="isLoading">
                <result-meta><div class="loader"></div></result-meta>
            </li>
            <li class="result-meta with-bg" v-if="results.length && totalCount > results.length">
                <result-meta>
                    <a @mousedown="goToPath(paths.searchPage + keyword + '#user')">View all {{ totalCount }} users</a>
                </result-meta>
            </li>
            <li class="result-meta" v-if="!results.length">
                <result-meta>No users were found matching "{{ keyword }}"</result-meta>
            </li>
            <li class="result-meta with-bg" v-if="!results.length">
                <result-meta>
                    <a @mousedown="goToPath(paths.searchPage + keyword + '#all')">Search everything for "{{ keyword }}"</a>
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

                if (self.keyword.length >= 3) {
                    self.isOpen = true;
                    self.isLoading = true;
                    self.filterResults();
                }
            }, 500),

            onArrowDown: function() {
                if (this.arrowCounter < this.results.length - 1) {
                    this.arrowCounter = this.arrowCounter + 1;
                }
            },

            onArrowUp: function() {
                if (this.arrowCounter > 0) {
                    this.arrowCounter = this.arrowCounter - 1;
                }
            },

            onEnter: function(id) {
                this.goToProfilePage(this.results[this.arrowCounter].id);
            },

            lostFocus: function(event) {
                this.isFocused = false;
                this.arrowCounter = -1;
            },

            goToProfilePage: function(id) {
                window.location.href = this.paths.userPage + id;
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
    
    img {
        float: left;
        padding-right: 1rem;
    }

    .global-search {
        z-index: 1000;
    }

    .list-item {
        padding-top: 0 !important;
        padding-bottom: 0;
        clear: both;
        height: 100%;
        border-bottom: 1px solid #CCC;
    }

    .list-item .name {
        font-size: 1rem;
        padding-top: .5rem;
        margin-bottom: 0;
    }

    .loader {
        height: 1rem;
        width: 1rem;
        border-radius: .20rem;
        margin: 1rem auto;
    }

    .matching-users .list-item
    {
        border-bottom: 0;
        padding: .5rem 0 .5rem 0 !important;
    }

    .matching-users .list-item .meta {
        margin-bottom: 0;
    }

    .meta {
        margin-bottom: .65rem;
    }

    .result {
        list-style: none;
        text-align: left;
        padding: 0;
        cursor: pointer;
    }

    .name {
        color: #0066b0;
    }

    .is-active .name,
    .result:hover,
    .name:hover,
    a:hover {
        color: #FFF !important;
    }

    .result:hover,
    .result .is-active {
        background-color: #0066b0;
        color: #FFF !important;
    }

    .results {
        padding: 0;
        margin: 0;
        border: 1px solid #0066b0;
        height: auto;
        background-color: #FFF;
        z-index: 10000;
    }

    .result-meta {
        padding: .5rem 0 !important;
        list-style: none;
    }

    .result-meta .list-item {
        border-bottom: 0;
    }

    .with-bg {
        background-color: #f1f9ff;
    }

    .inner-wrap:not(.left-open):not(.right-open) .global-search:hover,
    .inner-wrap:not(.left-open):not(.right-open) .global-search.focus {
        padding-left: 9rem;
    }
</style>

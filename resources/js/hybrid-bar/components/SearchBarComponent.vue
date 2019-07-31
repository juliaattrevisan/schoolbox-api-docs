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
               @keydown.ctrl.enter="onEnter"
               v-click-outside="lostFocus"
        />
        <ul class="results information-list" v-show="isFocused && isOpen">
            <li class="result-meta" v-if="results.length && results.length !== totalCount" @click.prevent>
                <result-meta>Showing {{ results.length }} of {{ totalCount }} matching users</result-meta>
            </li>
            <li v-for="(result, i) in results" :key="i" class="result">
                <a class="link-row">
                    <div class="compact card small-12" v-bind:class="{ 'is-active': i === arrowCounter }" @mouseover="arrowCounter = i">
                        <div>
                            <img v-bind:src="formatPortrait(result.id)">
                            <p class="name">
                                {{ result.formattedName }}
                                <span class="pipe meta">{{ formatMeta(result) }}</span>
                            </p>
                        </div>
                    </div>
                </a>
            </li>
            <li class="result-meta" v-if="isLoading">
                <result-meta><div class="loader"></div></result-meta>
            </li>
            <li class="result-meta with-bg" v-if="results.length && totalCount > results.length" @mousedown="goToPath(paths.searchPage + keyword + '#user')">
                <result-meta>
                    View all {{ totalCount }} users
                </result-meta>
            </li>
            <li class="result-meta" v-if="!isLoading && !results.length">
                <result-meta>No users were found matching "{{ keyword }}"</result-meta>
            </li>
            <li class="result-meta with-bg" v-if="!isLoading && !results.length" @mousedown="goToPath(paths.searchPage + keyword + '#all')">
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
    .inner-wrap:not(.left-open):not(.right-open) .global-search:hover,
    .inner-wrap:not(.left-open):not(.right-open) .global-search.focus {
        padding-left: 9rem;
    }
</style>

<template>
    <form id="search" class="global-search" action="/search" v-bind:class="{focus: isFocused}">
        <input id="search-field"
               type="text"
               name="keyword"
               placeholder="Search"
               autocomplete="off"
               v-model.trim="keyword"
               @focusin="isFocused=true"
               @blur="isFocused=false"
               @input="onChange"
        />
        <ul class="results" v-show="isFocused && isOpen">
            <li class="result-meta" v-if="results.length && results.length !== totalCount">
                <result-meta>Showing {{ results.length }} of {{ totalCount }} matching users</result-meta>
            </li>
            <li v-for="(result, i) in results" :key="i" class="result actions-small-1">
                <div class="list-item small-12">
                    <a v-bind:href="'/search/user/' + result.id">
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
                    <a v-bind:href="'/search?keyword=' + keyword + '#user'">View all {{ totalCount }} users</a>
                </result-meta>
            </li>
            <li class="result-meta" v-if="!results.length">
                <result-meta>No users were found matching "{{ keyword }}"</result-meta>
            </li>
            <li class="result-meta with-bg" v-if="!results.length">
                <result-meta>
                    <a href="#">Search everything for "{{ keyword }}"</a>
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
        props: {

        },
        data: function () {
            return {
                isFocused: false,
                keyword: '',
                results: [],
                isOpen: false,
                isLoading: true,
                noResults: true,
                totalCount: 0
            }
        },
        methods: {
            onChange: function(event) {
                var self = this;
                if (self.keyword.length >= 3) {
                    this.isOpen = true;
                    this.isFocused = true;
                    this.isLoading = true;
                    this.filterResults();
                    this.results = [];
                }
            },

            filterResults: function() {
                var self = this;
                axios.get('/user/search?keyword=' + self.keyword).then(function (response) {
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
                return '/portrait.php?id=' + id + '&size=square64';
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

    .result a:hover {
        color: #FFF;
    }

    .result:hover {
        background-color: #0066b0;
        color: #FFF;
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
        padding: 1rem 0 !important;
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

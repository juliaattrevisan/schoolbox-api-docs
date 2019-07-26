<template>
    <form id="search" class="global-search" action="/search" v-bind:class="{focus: isFocused}">
        <input id="search-field"
               type="text"
               name="keyword"
               placeholder="Search"
               autocomplete="off"
               v-model="keyword"
               @focusin="isFocused = true"
               @blur="isFocused = false"
               @input="onChange"
        />
        <ul class="results" v-show="isFocused && isOpen">
            <li class="result-meta" v-if="viewAllUsers">
                <result-meta>Showing 5 of 25 matching users</result-meta>
            </li>
            <li v-for="(result, i) in results" :key="i" class="result actions-small-1">
                <div class="list-item small-12">
                    <a href="/search/user/6">
                        <img src="/portrait.php?userId=506&amp;size=square64">
                        <p class="name">{{ result }}</p>
                        <p class="meta">Parent / Teacher / Year XX Student</p>
                    </a>
                </div>
            </li>
            <li class="result-meta" v-if="isLoading">
                <result-meta><div class="loader"></div></result-meta>
            </li>
            <li class="result-meta with-bg" v-if="viewAllUsers">
                <result-meta>
                    <a href="/search/user/6">View all 25 users here</a>
                </result-meta>
            </li>
            <li class="result-meta" v-if="viewAllUsers">
                <result-meta>No users were found matching "{{ keyword }}"</result-meta>
            </li>
            <li class="result-meta with-bg" v-if="viewAllUsers">
                <result-meta>
                    <a href="/search/user/6">Search everything for "{{ keyword }}"</a>
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
            items: {
                type: Array,
                required: false,
                default: () => [],
            }
        },
        data: function () {
            return {
                isFocused: false,
                keyword: '',
                results: [],
                isOpen: false,
                isLoading: true,
                noResults: true,
                viewAllUsers: true
            }
        },
        methods: {
            onChange: function(event) {
                this.isOpen = true;
                this.isFocused = true;
                this.filterResults();
            },

            filterResults: function() {
                this.results = this.items.filter(item => item.toLowerCase().indexOf(this.keyword.toLowerCase()) > -1);
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

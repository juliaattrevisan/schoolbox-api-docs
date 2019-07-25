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
            <li v-for="(result, i) in results" :key="i" class="result actions-small-1">
                <div class="list-item">
                    <div class="small-12">
                        <a href="/search/user/6">
                            <img src="/portrait.php?userId=506&amp;size=square64">
                            <h3>{{ result }}</h3>
                        </a>
                        <h4 class="meta">meta</h4>
                    </div>
                </div>
            </li>
            <li class="loading" v-if="isLoading">
                Loading results...
            </li>
            <li class="loading" v-if="viewAllUsers">
                View all 25 users
            </li>
            <li class="loading" v-if="noResults">
                No users were found matching "{{ keyword }}"
            </li>
            <li class="loading" v-if="noResults">
                Search everything for "{{ keyword }}"
            </li>

        </ul>
        <label data-jsubmit for="search-field" class="icon-search no-text"></label>
    </form>
</template>

<script>
    export default {
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
                this.filterResults();
            },

            filterResults: function() {
                this.results = this.items.filter(item => item.toLowerCase().indexOf(this.keyword.toLowerCase()) > -1);
            }
        }
    }
</script>

<style scoped>

    .global-search {
        z-index: 1000;
    }

    .results {
        padding: 0;
        margin: 0;
        border: 1px solid #0066b0;
        height: auto;
        background-color: #FFF;
        z-index: 10000;
    }

    .result {
        list-style: none;
        text-align: left;
        padding: 4px 2px;
        cursor: pointer;
    }

    .result:hover {
        background-color: #0066b0;
        color: white;
    }
</style>

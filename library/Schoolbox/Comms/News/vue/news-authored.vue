<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import Paginate from "vuejs-paginate-next";
import MediaArticle from "@schoolboxVue/components/media-article";
import { fetchAsJson } from "@js/fetch/fetch";

export default {
    components: {
        Paginate,
        MediaArticle,
    },
    props: {
        pageNumber: Number, // The start page on the pagination
        tabSelected: String, // The start selected tab, just used for active styling
        newsRoute: String,
        limitPerPage: Number,
        topicContainerId: Number,
        localeNews: String,
        localeNewsPlural: String,
        canCreateNews: Boolean,
        createNewsRoute: String,
    },
    data: function () {
        return {
            isArticlesLoaded: false, //show skeleton display otherwise
            activePageNumber: 1,
            activeTabSelected: "",
            statuses: {
                All: 0,
                Drafted: 0,
                "Awaiting Moderation": 0,
                Rejected: 0,
                "Awaiting Publication": 0,
                Published: 0,
                Archived: 0,
            },
            allArticles: [],
            filteredArticles: [], // A container for a filtered list of authored articles, Will be populated as all articles on load
        };
    },
    computed: {
        //when only those with posts need to be displayed
        activeStatuses: function () {
            return Object.keys(this.statuses).reduce((accumulator, currentValue) => {
                if (this.statuses[currentValue] > 0) {
                    accumulator[currentValue] = this.statuses[currentValue];
                }
                return accumulator;
            }, {});
        },

        // The total pages to show for pagination buttons
        pageCount: function () {
            return Math.ceil(this.filteredArticles.length / this.limitPerPage);
        },

        // The current set of articles showing for the page
        currentArticles: function () {
            if (this.filteredArticles.length === 0) {
                return [];
            }
            let offset = (this.activePageNumber - 1) * this.limitPerPage;
            let total = offset + this.limitPerPage;
            return this.filteredArticles.slice(offset, total);
        },
    },
    mounted: function () {
        this.activePageNumber = this.pageNumber;
        this.activeTabSelected = this.tabSelected;

        this.getNews();
        this.initWindowStateChangeListener();
    },
    methods: {
        /**
         * Load up all the news linked to the current user
         *
         */
        getNews: function () {
            fetchAsJson(this.newsRoute)
                .then((response) => {
                    this.allArticles = response;
                    this.filteredArticles = response;

                    //populate statuses for tab selections
                    response.forEach((article) => {
                        this.statuses["All"]++;
                        this.statuses[article.status]++;
                    });

                    // In the case that this page has loaded with a page and/or tab in the URL params - update the page to reflect those params
                    if (this.activeTabSelected !== "All") {
                        this.onTabChange(this.activeTabSelected, true, true);
                    }
                    if (this.activePageNumber > 1) {
                        this.onPaginate(this.activePageNumber, true);
                    }

                    // Reinit foundation for the kebab dropdowns, for some reason this has to happen just very slightly delayed :|
                    setTimeout(function () {
                        $(document).foundation();
                    }, 1);

                    this.isArticlesLoaded = true;
                })
                .catch((error) => {
                    console.error(error);
                });
        },

        /**
         *  Listen for window state change, to manage changes to pagination or tabs
         *
         */
        initWindowStateChangeListener: function () {
            window.onpopstate = (event) => {
                if (!event.state) {
                    // Return to defaults
                    this.activePageNumber = 1;
                    this.onPaginate(this.activePageNumber, true);
                    this.activeTabSelected = "All";
                    this.onTabChange(this.activeTabSelected, true, true);
                } else {
                    if (event.state.page) {
                        this.activePageNumber = event.state.page;
                        this.onPaginate(event.state.page, true);
                    }
                    if (event.state.tab) {
                        this.activeTabSelected = event.state.tab;
                        this.onTabChange(event.state.tab, true, true);
                    }
                }
            };
        },

        /**
         * On change of page
         *
         * @param {int} page - The page to change to
         * @param {boolean} holdState - Whether to change the window's history or not
         */
        onPaginate: function (page, holdState) {
            // Update the window history
            if (!holdState) {
                var params;
                if (this.activeTabSelected === "All") {
                    params = "?p=" + page;
                } else {
                    params = "?p=" + page + "&status=" + this.activeTabSelected;
                }
                window.history.pushState({ page: page, tab: this.activeTabSelected }, "", params);
            }
        },

        /**
         * Changing status tab
         *
         * @param {string} tab - The tab to change to
         * @param {boolean} persistPage
         * @param {boolean} holdState - Whether to change the window's history or not
         */
        onTabChange: function (tab, persistPage, holdState) {
            this.activeTabSelected = tab;
            if (!persistPage) {
                this.activePageNumber = 1;
            }
            if (tab === "All") {
                // Reset back to original set
                this.filteredArticles = this.allArticles;
            } else {
                // Filter by tab selected
                var filteredArticles = this.allArticles.filter(function (article) {
                    return article.status === tab;
                });
                this.filteredArticles = filteredArticles;
            }
            // Update the window history
            if (!holdState) {
                window.history.pushState({ tab: tab, activePageNumber: this.activePageNumber }, "", "?status=" + tab);
            }
        },
    },
};
</script>

<template>
    <div>
        <section>
            <dl
                v-if="isArticlesLoaded"
                class="tabs"
                :id="topicContainerId"
                v-bind:style="isArticlesLoaded && !allArticles.length ? 'display:none' : ''"
            >
                <dd
                    v-for="(statusCount, status) in activeStatuses"
                    :class="{ active: activeTabSelected === status }"
                    :key="status"
                >
                    <a @click="onTabChange(status)">{{ status }}</a>
                </dd>
            </dl>
            <dl v-else class="tabs" :id="topicContainerId">
                <dd class="skeleton" v-for="(n, index) in 3" :key="index">
                    <a></a>
                </dd>
            </dl>
            <div class="tabs-content no-margin">
                <ul v-if="isArticlesLoaded" class="information-list">
                    <li v-for="article in currentArticles" :key="article.id" class="actions-small-1">
                        <div class="list-item">
                            <div class="small-12 card">
                                <a v-if="article.featureImage" :href="article._links.self.href">
                                    <img
                                        :src="article.featureImage._links.image.href + '&size=square64'"
                                        alt="featured image"
                                    />
                                </a>
                                <h3>
                                    <a v-if="article.title" :href="article._links.self.href">{{ article.title }}</a>
                                    <a v-else :href="article._links.self.href">(untitled)</a>
                                </h3>
                                <p class="meta">
                                    <b>{{ article.status }}</b>
                                    <time :title="article.statusDate.long">{{ article.statusDate.relativeTime }}</time>
                                </p>
                            </div>
                        </div>
                        <article>
                            <media-article tag="div" :content="article.blurb"></media-article>
                            <div v-if="article.readMore.available" class="article-read-more">
                                <a :href="article._links.self.href">{{ article.readMore.string }}</a>
                            </div>
                        </article>
                        <nav>
                            <a
                                v-if="article._links.update.auth"
                                :href="article._links.update.href"
                                class="icon-modify no-text"
                                :title="'Modify this ' + localeNews"
                            ></a>
                        </nav>
                    </li>
                </ul>
                <ul v-else class="information-list">
                    <li v-for="(n, index) in 3" :key="index" class="actions-small-1">
                        <div class="list-item">
                            <div class="small-12 card skeleton">
                                <div class="img"></div>
                                <h3></h3>
                                <p class="meta"></p>
                            </div>
                        </div>
                    </li>
                </ul>
                <div v-if="isArticlesLoaded && filteredArticles.length === 0" class="empty-state no-margin">
                    <i class="icon-news"></i>
                    <p>You haven't authored any {{ localeNewsPlural }}.</p>
                    <p v-if="canCreateNews">
                        <a :href="createNewsRoute">Create {{ localeNews }}</a
                        >.
                    </p>
                </div>
            </div>
            <!-- <div class="tabs-content no-margin"> -->
        </section>
        <paginate
            v-if="currentArticles.length && pageCount > 1"
            v-model="activePageNumber"
            :page-count="pageCount"
            :container-class="'pagination'"
            :prev-text="'‹'"
            :next-text="'›'"
            :prev-link-class="'arrow'"
            :next-link-class="'arrow'"
            :disabled-class="'unavailable'"
            :click-handler="onPaginate"
        >
        </paginate>
    </div>
</template>

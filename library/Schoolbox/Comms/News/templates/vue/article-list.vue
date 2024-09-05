<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import Paginate from "vuejs-paginate-next";
import LayoutList from "./layout-list.vue";
import LayoutListReversed from "./layout-list-reversed.vue";
import LayoutCard from "./layout-card.vue";
import articleListMixin from "./article-list-mixin";

export default {
    name: "article-list",
    mixins: [articleListMixin],
    props: ["data"],
    components: {
        Paginate,
        LayoutList,
        LayoutListReversed,
        LayoutCard,
    },
    data() {
        return {
            queryStringPage: this.data.queryStringPage,
            queryStringReferrer: this.data.queryStringReferrer,
            queryStringTopic: this.data.queryStringTopic,

            topicAll: this.data.topicAll,
            // showTopicByDefault: accessed via this.data.showTopicByDefault
            changeTabOnMeta: this.data.changeTabOnMeta,

            // initialPage: accessed via this.data.initialPage
            limit: this.data.limit,
            // disablePagination: accessed via this.data.disablePagination

            referrer: this.data.referrer,

            compactMode: this.data.compactMode,
            // showMore: accessed via this.data.showMore
            itemLayout: this.data.itemLayout,
            fallbackImage: this.data.fallbackImage,

            getIndexRoute: this.data.getIndexRoute,
            // getArticlesRoute: accessed via this.data.getArticlesRoute
            // getSavedArticlesRoute: accessed via this.data.getSavedArticlesRoute

            configSaveForLater: this.data.configSaveForLater,
            configSearchTypeLimit: this.data.configSearchTypeLimit,
            localeNewsPlural: this.data.localeNewsPlural,

            allArticles: this.data.allArticles,
            savedArticleIds: this.data.savedArticleIds,
            filteredArticles: this.data.filteredArticles,
            topics: this.data.topics,

            page: this.data.page,
            articlesLoading: this.data.articlesLoading,
            tabSelected: this.data.tabSelected,
            initialTopic: this.data.initialTopic,
        };
    },
    mounted: function () {
        if (this.data.getSavedArticlesRoute) {
            fetch(this.data.getSavedArticlesRoute, { headers: getHeaders })
                .then((response) => response.json())
                .then((data) => {
                    this.savedArticleIds = data;
                })
                .catch((error) => {
                    console.warn(error);
                });
        }

        // Find some articles
        fetch(this.data.getArticlesRoute)
            .then((response) => response.json())
            .then((data) => {
                const articles = data;

                this.articlesLoading = false;
                this.allArticles = articles;
                this.filteredArticles = articles;

                // Start an array of topics, with All
                const topics = [this.data.topicAll];

                // Add topics from the articles returned
                const topicsAdded = [];
                articles.map((article) => {
                    article.topics.map((topic) => {
                        if (topicsAdded.indexOf(topic.slug) === -1) {
                            topicsAdded.push(topic.slug);
                            // WARNING: do not remove the space inside the braces below, phplib will eat it
                            topics.push(Object.assign({}, topic));
                        }
                    });
                });

                // Sort topics based on sequence
                topics.sort((a, b) => {
                    return a.sequence - b.sequence;
                });

                // Send the array to Vue
                this.topics = topics;

                // Set default selected tab (if not already set via twig above)
                if (!this.tabSelected) {
                    if (this.data.showTopicByDefault && this.topics[1]) {
                        this.tabSelected = this.topics[1].slug;
                    } else {
                        this.tabSelected = this.topicAll.slug;
                    }
                }
                this.initialTopic = this.tabSelected;

                // In the case that this page has loaded with a page and/or tab in the URL params - update the page to reflect those params
                if (this.tabSelected !== this.data.topicAll.slug) {
                    this.onTabChange(this.tabSelected, true, true);
                }
                if (this.page > 1) {
                    this.onPaginate(this.page, true);
                }
            })
            .catch((error) => {
                console.warn(error);
            });

        // Listen for window state change
        if (this.data.modifyWindowState) {
            window.onpopstate = (event) => {
                if (!event.state) {
                    // Return to defaults
                    this.page = this.data.initialPage;
                    this.onPaginate(this.page, true);
                    this.tabSelected = this.initialTopic;
                    this.onTabChange(this.tabSelected, true, true);
                } else {
                    if (event.state.page) {
                        this.page = event.state.page;
                        this.onPaginate(event.state.page, true);
                    }

                    if (event.state.tab) {
                        this.tabSelected = event.state.tab;
                        this.onTabChange(event.state.tab, true, true);
                    }
                }
            };
        }
    },
    methods: {
        /*
         * On change of page
         *
         * @param {int} page - The page to change to
         * @param {boolean} holdState - Whether to change the window's history or not
         */
        onPaginate: function (page, holdState) {
            // Update the window history
            if (!holdState && this.modifyWindowState) {
                var params;
                if (this.tabSelected === this.topicAll.slug) {
                    params = "?" + this.data.queryStringPage + "=" + page;
                } else {
                    params =
                        "?" +
                        this.data.queryStringPage +
                        "=" +
                        page +
                        "&" +
                        this.queryStringTopic +
                        "=" +
                        this.tabSelected;
                }
                window.history.pushState({ page: page, tab: this.tabSelected }, "", params);
            }
        },

        /*
         * Changing topic tab.
         *
         * @param {string} tab - The tab to change to (slug)
         * @param {boolean} persistPage
         * @param {boolean} holdState - Whether to change the window's history or not
         * @param {boolean} dispatchEvent - Whether to trigger a change tab event
         */
        onTabChange: function (tab, persistPage, holdState, dispatchEvent = false) {
            var self = this;

            self.tabSelected = tab;
            if (!persistPage) {
                self.page = 1;
            }

            if (tab === this.data.topicAll.slug) {
                // Reset back to original set
                self.filteredArticles = self.allArticles;
            } else {
                // Filter articles by tab selected
                var filteredArticles = self.allArticles.filter(function (article) {
                    return article.topics.some(function (topic) {
                        // Return true if the selected topic appears once
                        return topic.slug === tab;
                    });
                });

                self.filteredArticles = filteredArticles;
            }

            // Update the window history
            if (!holdState && this.data.modifyWindowState) {
                window.history.pushState({ tab: tab, page: self.page }, "", "?" + self.queryStringTopic + "=" + tab);
            }

            // Update the RSS link (which lives in a different Vue component)
            // The rssLink component only exists on the news feed page, not on the dashboard
            if (typeof rssLink !== "undefined") {
                rssLink.topic = tab;
            }

            if (dispatchEvent) {
                const event = new CustomEvent("article-list-mixin__onTabChange", {
                    detail: {
                        queryStringTopic: this.queryStringTopic,
                        tab: tab,
                    },
                });
                document.dispatchEvent(event);
            }
        },
    },
    computed: {
        // The total pages to show for pagination buttons
        pageCount: function () {
            return Math.ceil(this.filteredArticles.length / this.data.configSearchTypeLimit);
        },

        // The current set of articles showing for the page
        currentArticles: function () {
            // Short circuit if there's nothing in filteredArticles
            if (!this.filteredArticles.length) {
                return [];
            }

            // If there's a limit- just return 0 to that limit
            if (this.limit) {
                return this.filteredArticles.slice(0, this.limit);
            }

            // If pagination is disabled- just return all the filtered articles
            // At the time of writing- this isn't reachable because disablePagination is only on when limit is also on
            // But, include this in case this is ever wanted
            if (this.data.disablePagination) {
                return this.filteredArticles;
            }

            // Otherwise- work out the current set of articles based on the items per page, and the current page
            var limit = this.data.configSearchTypeLimit;
            var offset = (this.page - 1) * limit;
            var total = offset + limit;

            return this.filteredArticles.slice(offset, total);
        },

        // When to show the 'show more' component action
        showMoreLink: function () {
            return !this.articlesLoading && this.data.showMore && this.currentArticles.length;
        },
    },
};
</script>
<template>
    <div>
        <section>
            <dl class="tabs" v-if="this.allArticles.length || this.articlesLoading">
                <dd v-for="(topic, index) in this.topics" :class="{ active: tabSelected === topic.slug }" :key="index">
                    <a @click="onTabChange(topic.slug, false, false, true)">{{ topic.name }}</a>
                </dd>
                <template v-if="this.articlesLoading">
                    <dd class="skeleton" v-for="n in 3" :key="n">
                        <a></a>
                    </dd>
                </template>
            </dl>
            <div class="tabs-content no-margin">
                <template v-if="this.itemLayout === 'list'">
                    <layout-list
                        :query-string-referrer="this.queryStringReferrer"
                        :query-string-topic="this.queryStringTopic"
                        :change-tab-on-meta="this.changeTabOnMeta"
                        :referrer="this.referrer"
                        :compact-mode="this.compactMode"
                        :fallback-image="this.fallbackImage"
                        :get-index-route="this.getIndexRoute"
                        :config-save-for-later="this.configSaveForLater"
                        :locale-news-plural="this.localeNewsPlural"
                        :saved-article-ids="this.savedArticleIds"
                        :articles-loading="this.articlesLoading"
                        :current-articles="this.currentArticles"
                    ></layout-list>
                </template>
                <template v-else-if="this.itemLayout === 'list-reversed'">
                    <layout-list-reversed
                        :query-string-referrer="this.queryStringReferrer"
                        :query-string-topic="this.queryStringTopic"
                        :change-tab-on-meta="this.changeTabOnMeta"
                        :referrer="this.referrer"
                        :compact-mode="this.compactMode"
                        :fallback-image="this.fallbackImage"
                        :get-index-route="this.getIndexRoute"
                        :config-save-for-later="this.configSaveForLater"
                        :locale-news-plural="this.localeNewsPlural"
                        :saved-article-ids="this.savedArticleIds"
                        :articles-loading="this.articlesLoading"
                        :current-articles="this.currentArticles"
                    ></layout-list-reversed>
                </template>
                <template v-else-if="this.itemLayout === 'card'">
                    <layout-card
                        :query-string-referrer="this.queryStringReferrer"
                        :query-string-topic="this.queryStringTopic"
                        :change-tab-on-meta="this.changeTabOnMeta"
                        :referrer="this.referrer"
                        :compact-mode="this.compactMode"
                        :fallback-image="this.fallbackImage"
                        :get-index-route="this.getIndexRoute"
                        :config-save-for-later="this.configSaveForLater"
                        :locale-news-plural="this.localeNewsPlural"
                        :saved-article-ids="this.savedArticleIds"
                        :articles-loading="this.articlesLoading"
                        :current-articles="this.currentArticles"
                    ></layout-card>
                </template>

                <div class="empty-state no-margin" v-if="!this.filteredArticles.length && !this.articlesLoading">
                    <i class="icon-news"></i>
                    <p>No {{ this.data.localeNewsPlural }} to view at this time.</p>
                </div>
            </div>

            <div class="component-action" v-if="this.showMoreLink">
                <section>
                    <a :href="this.getIndexRoute + '?' + this.queryStringTopic + '=' + this.tabSelected" class="button"
                        >View More</a
                    >
                </section>
            </div>
        </section>
        <paginate
            v-if="!this.data.disablePagination && this.currentArticles.length && this.pageCount > 1"
            v-model="page"
            :page-count="pageCount"
            :container-class="'pagination'"
            :prev-text="'‹'"
            :next-text="'›'"
            :prev-link-class="'arrow'"
            :next-link-class="'arrow'"
            :disabled-class="'unavailable'"
            :click-handler="onPaginate"
        ></paginate>
    </div>
</template>

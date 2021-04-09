<script>
    import VuejsPaginate from 'vuejs-paginate';
    import LayoutList from './layout-list.vue';
    import LayoutListReversed from './layout-list-reversed.vue';
    import LayoutCard from './layout-card.vue';
    import articleListMixin from './article-list-mixin';
    import axios from 'axios';

    export default {
        name: "article-list",
        mixins: [articleListMixin],
        props: [
            'data'
        ],
        components: {
            'Paginate': VuejsPaginate,
            'LayoutList': LayoutList,
            'LayoutListReversed': LayoutListReversed,
            'LayoutCard': LayoutCard
        },
        data() {
            return {
                'queryStringPage': this.data.queryStringPage,
                'queryStringReferrer': this.data.queryStringReferrer,
                'queryStringTopic': this.data.queryStringTopic,

                'topicAll': this.data.topicAll,
                // showTopicByDefault: accessed via this.data.showTopicByDefault
                'changeTabOnMeta': this.data.changeTabOnMeta,

                // initialPage: accessed via this.data.initialPage
                'limit': this.data.limit,
                // disablePagination: accessed via this.data.disablePagination

                'referrer': this.data.referrer,

                'compactMode': this.data.compactMode,
                // showMore: accessed via this.data.showMore
                'itemLayout': this.data.itemLayout,
                'fallbackImage': this.data.fallbackImage,

                'getIndexRoute': this.data.getIndexRoute,
                // getArticlesRoute: accessed via this.data.getArticlesRoute
                // getSavedArticlesRoute: accessed via this.data.getSavedArticlesRoute

                'configSaveForLater': this.data.configSaveForLater,
                'configSearchTypeLimit': this.data.configSearchTypeLimit,
                'localeNewsPlural': this.data.localeNewsPlural,

                'allArticles': this.data.allArticles,
                'savedArticleIds': this.data.savedArticleIds,
                'filteredArticles': this.data.filteredArticles,
                'topics': this.data.topics,

                'page': this.data.page,
                'articlesLoading': this.data.articlesLoading,
                'tabSelected': this.data.tabSelected,
                'initialTopic': this.data.initialTopic
            }
        },
        mounted: function () {
            var self = this;

            axios.get(self.data.getSavedArticlesRoute, {headers: getHeaders}).then(function(response) {
                self.savedArticleIds = response.data;
            }).catch(function (error) {
                console.log(error);
            });

            // Find some articles
            axios.get(self.data.getArticlesRoute, {headers: getHeaders}).then(function(response) {
                var articles = response.data;

                self.articlesLoading = false;
                self.allArticles = articles;
                self.filteredArticles = articles;

                // Start an array of topics, with All
                var topics = [
                    self.data.topicAll
                ];

                // Add topics from the articles returned
                var topicsAdded = [];
                articles.map(function(article) {
                    article.topics.map(function(topic) {
                        if (topicsAdded.indexOf(topic.slug) === -1) {
                            topicsAdded.push(topic.slug);
                            // WARNING: do not remove the space inside the braces below, phplib will eat it
                            topics.push(Object.assign({ }, topic));
                        }
                    });
                });

                // Sort topics based on sequence
                topics.sort(function(a, b) {
                    return a.sequence - b.sequence;
                });

                // Send the array to Vue
                self.topics = topics;

                // Set default selected tab (if not already set via twig above)
                if (!self.tabSelected) {
                    if (self.data.showTopicByDefault && self.topics[1]) {
                        self.tabSelected = self.topics[1].slug;
                    } else {
                        self.tabSelected = self.topicAll.slug;
                    }
                }
                self.initialTopic = self.tabSelected;

                // In the case that this page has loaded with a page and/or tab in the URL params - update the page to reflect those params
                if (self.tabSelected !== self.data.topicAll.slug) {
                    self.onTabChange(self.tabSelected, true, true);
                }
                if (self.page > 1) {
                    self.onPaginate(self.page, true);
                }

            }).catch(function (error) {
                console.log(error);
            });

            // Listen for window state change
            if (self.data.modifyWindowState) {
                window.onpopstate = function(event) {
                    if (!event.state) {
                        // Return to defaults
                        self.page = self.data.initialPage;
                        self.onPaginate(self.page, true);
                        self.tabSelected = self.initialTopic;
                        self.onTabChange(self.tabSelected, true, true);
                    } else {
                        if (event.state.page) {
                            self.page = event.state.page;
                            self.onPaginate(event.state.page, true);
                        }

                        if (event.state.tab) {
                            self.tabSelected = event.state.tab;
                            self.onTabChange(event.state.tab, true, true);
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
                        params = '?' + this.data.queryStringPage + '=' + page;
                    } else {
                        params = '?' + this.data.queryStringPage + '=' + page + '&' + this.queryStringTopic + '=' + this.tabSelected;
                    }
                    window.history.pushState({page: page, tab: this.tabSelected}, '', params);
                }
            }
        },
        computed: {
            // The total pages to show for pagination buttons
            pageCount: function () {
                return Math.ceil(this.filteredArticles.length / this.data.configSearchTypeLimit);
            },

            // The current set of articles showing for the page
            currentArticles: function() {
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
                var offset = (this.page-1) * limit;
                var total = offset + limit;

                return this.filteredArticles.slice(offset, total);
            },

            // When to show the 'show more' component action
            showMoreLink: function() {
                return !this.articlesLoading && this.data.showMore && this.currentArticles.length;
            }
        }
    }
</script>
<template>
    <div>
        <section>
            <dl class="tabs" v-if="this.allArticles.length || this.articlesLoading">
                <dd v-for="(topic, index) in this.topics" :class="{active: tabSelected === topic.slug}" :key="index">
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
                    <a :href="this.getIndexRoute + '?' + this.queryStringTopic + '=' + this.tabSelected" class="button">View More</a>
                </section>
            </div>
        </section>
        <Paginate
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
        ></Paginate>
    </div>
</template>

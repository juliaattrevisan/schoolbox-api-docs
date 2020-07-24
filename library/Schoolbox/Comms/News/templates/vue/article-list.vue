<script>
    export default {
        name: "article-list",
        props: [
            'data'
        ],
        delimiters: ['${', '}'],
        components: {
            'Paginate': VuejsPaginate
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
            },

            /*
             * Changing topic tab
             *
             * @param {string} tab - The tab to change to (slug)
             * @param {boolean} persistPage
             * @param {boolean} holdState - Whether to change the window's history or not
             */
            onTabChange: function (tab, persistPage, holdState) {
                var self = this;

                self.tabSelected = tab;
                if (!persistPage) {
                    self.page = 1;
                }

                if (tab === this.topicAll.slug) {
                    // Reset back to original set
                    self.filteredArticles = self.allArticles;
                } else {
                    // Filter articles by tab selected
                    var filteredArticles = self.allArticles.filter(function (article) {
                        return article.topics.some(function(topic) { // Return true if the selected topic appears once
                            return topic.slug === tab;
                        });
                    });

                    self.filteredArticles = filteredArticles;
                }

                // Update the window history
                if (!holdState && this.data.modifyWindowState) {
                    window.history.pushState({tab: tab, page: self.page}, '', '?' + self.queryStringTopic + '=' + tab);
                }

                // Update the RSS link (which lives in a different Vue component)
                // The rssLink component only exists on the news feed page, not on the dashboard
                if (typeof rssLink !== 'undefined') {
                    rssLink.topic = tab;
                }
            },

            /**
             * Save an article for later
             *
             * @param article
             */
            saveForLater: function(article) {
                var self = this;

                var requestURL,
                        toastActivateMessage,
                        toastSuccessMessage,
                        toastFailMessage,
                        updateArticleIds;

                function removeArticleId(articleId) {
                    var articleIdIndex = self.savedArticleIds.indexOf(articleId)
                    self.savedArticleIds.splice(articleIdIndex, 1);
                }

                function addArticleId(articleId) {
                    self.savedArticleIds.splice(0, 0, articleId);
                }

                if (self.savedArticleIds.indexOf(article.id) >= 0 && article._links.unsaveForLater.auth) {
                    // Article is already saved; this is an "unsave" action
                    requestURL = article._links.unsaveForLater.href;
                    toastActivateMessage = 'Removing...';
                    toastSuccessMessage = 'Removed';
                    toastFailMessage = 'Unable to remove';
                    updateArticleIds = removeArticleId;
                } else if (article._links.saveForLater.auth) {
                    // Save the article for later
                    requestURL = article._links.saveForLater.href;
                    toastActivateMessage = 'Saving...';
                    toastSuccessMessage = 'Saved';
                    toastFailMessage = 'Unable to save';
                    updateArticleIds = addArticleId;
                }

                var $toast = $('#toaster');
                $toast.toastActivate({text: toastActivateMessage});
                axios.post(requestURL, [], { headers: postHeaders }).then(function () {
                    $toast.toastTimeout({text: toastSuccessMessage, duration: 2000});
                    updateArticleIds(article.id);
                }).catch(function (error) {
                    console.log(error);
                    $toast.toastTimeout({text: toastFailMessage, duration: 2000, css: 'alert'});
                });

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
                if (!this.data.filteredArticles.length) {
                    return [];
                }

                // If there's a limit- just return 0 to that limit
                if (this.limit) {
                    return this.data.filteredArticles.slice(0, this.limit);
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

                return this.data.filteredArticles.slice(offset, total);
            },

            // When to show the 'show more' component action
            showMoreLink: function() {
                return !this.articlesLoading && this.data.showMore && this.currentArticles.length;
            }
        },
        filters: {
            formatCommentCountMeta: function (value) {
                var suffix = ' comment';

                if (value > 1) {
                    suffix = ' comments';
                }

                return value + suffix;
            },
        }
    }
</script>
<template>
    <div>
        <section>
            <dl class="tabs" v-if="this.data.allArticles.length || this.data.articlesLoading">
                <dd v-for="topic in this.data.topics" :class="{active: this.data.tabSelected === topic.slug}">
                    <a @click="onTabChange(topic.slug)">${ topic.name }</a>
                </dd>
                <template v-if="this.data.articlesLoading">
                    <dd class="skeleton" v-for="n in 3">
                        <a></a>
                    </dd>
                </template>
            </dl>
            <div v-if="this.data.articlesLoading"></div>
            <div class="tabs-content no-margin">
                <ul class="information-list">
                    <template v-if="this.data.articlesLoading">
                        <li class="actions-small-1" v-for="n in 3">
                            <div class="list-item">
                                <div class="small-12 card skeleton">
                                    <div class="img"></div>
                                    <h3></h3>
                                    <p class="meta"></p>
                                </div>
                            </div>
                        </li>
                    </template>

                    <li class="actions-small-1" v-bind:class="{sticky: article.sticky}" v-for="article in this.currentArticles">
<!--                        <div class="list-item">-->
<!--                            <div class="small-12 card wrap-down">-->
<!--                                <template v-if="article.featureImage">-->
<!--                                    <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer">-->
<!--                                        <img :src="article.featureImage._links.image.href + '&size=square64'">-->
<!--                                    </a>-->
<!--                                </template>-->

<!--                                <h3>-->
<!--                                    <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer">-->
<!--                                        ${ article.title }-->
<!--                                    </a>-->
<!--                                </h3>-->

<!--                                <p class="meta">-->
<!--                                        <span>-->
<!--                                            <template v-if="article.sticky">Pinned by</template>-->
<!--                                            <template v-else>By</template>-->
<!--                                            <a v-if="article.author._links && article.author._links.profile" :href="article.author._links.profile.href">${ article.author.fullname }</a>-->
<!--                                            <template v-else>${ article.author.fullname }</template>-->
<!--                                        </span>-->
<!--                                    <span> &mdash; <time :title="article.publishAt.long">${ article.publishAt.relativeTime }</time></span>-->
<!--                                    <span v-if="article.topics.length">-->
<!--                                            in-->
<!--                                            <template v-for="(topic, index) in article.topics">-->
<!--                                                <template v-if="index">, </template>-->
<!--                                                <a v-if="changeTabOnMeta" @click="onTabChange(topic.slug)">${ topic.name }</a>-->
<!--                                                <a v-else :href="getIndexRoute + '?' + queryStringTopic + '=' + topic.slug">${ topic.name }</a>-->
<!--                                            </template>-->
<!--                                        </span>-->
<!--                                    <span v-if="article.metadata && article.metadata.commentCount > 0">-->
<!--                                        | <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer + '#discussion'">${ article.metadata.commentCount | formatCommentCountMeta }</a>-->
<!--                                        </span>-->
<!--                                </p>-->
<!--                            </div>-->
<!--                        </div>-->

<!--                        <nav>-->
<!--                            <a v-if="article._links.update.auth" :href="article._links.update.href" class="icon-modify no-text" :title="'Modify this ' + this.data.localeNewsPlural"></a>-->
<!--                            <a v-if="configSaveForLater" @click="saveForLater(article)"-->
<!--                               :class="[ savedArticleIds.indexOf(article.id) >= 0 ? 'icon-favourite': 'icon-favourite-hollow' ]"-->
<!--                               title="Save for later"></a>-->
<!--                        </nav>-->

<!--                        <article v-if="!compactMode">-->
<!--                            <div v-html="article.blurb"></div>-->
<!--                            <div class="article-read-more">-->
<!--                                <a :href="article._links.self.href + '?' + queryStringReferrer + '=' + referrer" v-html="article.readmore.string"></a>-->
<!--                            </div>-->
<!--                        </article>-->
                    </li>
                </ul>

<!--                <div class="empty-state no-margin" v-if="!filteredArticles.length && !articlesLoading">-->
<!--                    <i class="icon-news"></i>-->
<!--                    <p>No ${ localeNewsPlural } to view at this time.</p>-->
<!--                </div>-->
            </div>

<!--            <div class="component-action" v-if="showMoreLink">-->
<!--                <section>-->
<!--                    <a :href="getIndexRoute + '?' + queryStringTopic + '=' + tabSelected" class="button">View More</a>-->
<!--                </section>-->
<!--            </div>-->
        </section>
<!--        <Paginate-->
<!--                v-if="!this.data.disablePagination && this.data.currentArticles.length && this.data.pageCount > 1"-->
<!--                v-model="this.data.page"-->
<!--                :page-count="this.data.pageCount"-->
<!--                :container-class="'pagination'"-->
<!--                :prev-text="'‹'"-->
<!--                :next-text="'›'"-->
<!--                :prev-link-class="'arrow'"-->
<!--                :next-link-class="'arrow'"-->
<!--                :disabled-class="'unavailable'"-->
<!--                :click-handler="onPaginate"-->
<!--        ></Paginate>-->
    </div>
</template>

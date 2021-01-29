<template>

    <div>

        <section>
            
            <dl class="tabs" :id="config.topicContainerId" v-if="allArticles.length || articlesLoading">
                <dd v-for="(statusCount, status) in statuses" :class="{active: tabSelected === status}" v-if="statusCount">
                    <a @click="onTabChange(status)">{{ status }}</a>
                </dd>
                <dd class="skeleton" v-if="articlesLoading" v-for="n in 3">
                    <a></a>
                </dd>
            </dl>
            
            <div class="tabs-content no-margin">

                <ul class="information-list">

                    <li class="actions-small-1" v-if="articlesLoading" v-for="n in 3">
                        <div class="list-item">
                            <div class="small-12 card skeleton">
                                <div class="img"></div>
                                <h3></h3>
                                <p class="meta"></p>
                            </div>
                        </div>
                    </li>

                    <li class="actions-small-1" v-for="article in currentArticles">
                        <div class="list-item">
                            <div class="small-12 card">

                                <a :href="article._links.self.href" v-if="article.featureImage">
                                    <img :src="article.featureImage._links.image.href + '&size=square64'">
                                </a>

                                <h3><a v-if="article.title" :href="article._links.self.href">{{ article.title }}</a>
                                    <a v-else :href="article._links.self.href">(untitled)</a>
                                </h3>

                                <p class="meta">
                                    <b>{{article.status }}</b>
                                    <time :title="article.statusDate.long">{{ article.statusDate.relativeTime }}</time>
                                </p>
                            </div>
                        </div>

                        <article>
                            <div v-html="article.blurb"></div>
                            <div class="article-read-more">
                                <a v-if="article.readmore.available" :href="article._links.self.href" v-html="article.readmore.string"></a>
                            </div>
                        </article>
                        <nav>
                            <a v-if="article._links.update.auth" :href="article._links.update.href" class="icon-modify no-text" title="Modify this locale.news "></a>
                        </nav>
                    </li>
                </ul>

                <div class="tabs-content no-margin">

                    <ul class="information-list">

                        <li class="actions-small-1" v-if="articlesLoading" v-for="n in 3">
                            <div class="list-item">
                                <div class="small-12 card skeleton">
                                    <div class="img"></div>
                                    <h3></h3>
                                    <p class="meta"></p>
                                </div>
                            </div>
                        </li>

                        <li class="actions-small-1" v-for="article in currentArticles">
                            <div class="list-item">
                                <div class="small-12 card">

                                    <a :href="article._links.self.href" v-if="article.featureImage">
                                        <img :src="article.featureImage._links.image.href + '&size=square64'">
                                    </a>

                                    <h3><a v-if="article.title" :href="article._links.self.href">{{ article.title }}</a>
                                        <a v-else :href="article._links.self.href">(untitled)</a>
                                    </h3>

                                    <p class="meta">
                                        <b>{{ article.status }}</b>
                                        <time :title="article.statusDate.long">{{ article.statusDate.relativeTime }}</time>
                                    </p>
                                </div>
                            </div>

                            <article>
                                <div v-html="article.blurb"></div>
                                <div class="article-read-more">
                                    <a v-if="article.readmore.available" :href="article._links.self.href" v-html="article.readmore.string"></a>
                                </div>
                            </article>
                            <nav>
                                <a v-if="article._links.update.auth" :href="article._links.update.href" class="icon-modify no-text" title="Modify this locale.news"></a>
                            </nav>
                        </li>

                    </ul>

                    <!-- <div class="empty-state no-margin" v-if="!filteredArticles.length && !articlesLoading">
                    <i class="icon-news"></i>
                    <p>You haven't authored any {{ locale.news.plural }}.</p>
                    {% set createRoute = routeLookupIfAuthorized('commsNews.getCreate') %}
                    {% if createRoute %}<p><a href="{{ createRoute }}">Create {{ locale.news }}</a>.</p>{% endif %}
                    </div> -->

                </div><!-- <div class="tabs-content no-margin"> -->

            </div><!-- <div class="tabs-content no-margin"> -->

        </section><!-- <div class="tabs-content no-margin"> -->

    </div>

</template>

<script>
    
    // import VuejsPaginate from 'vuejs-paginate';
    import axios from 'axios';
    
    export default {
        components: {
            'paginate': VuejsPaginate
        },
        data: function() {
            return {

                // Whether the articles are still being loaded or not
                // Used to show the placeholder loader
                articlesLoading: true,

                // A unique list of statuses returned from the user's articles
                // Used to display tab options
                statuses: {},

                // *All* the author's articles
                allArticles: [],

                // A container for a filtered list of authored articles
                // Will be populated as all articles on load
                filteredArticles: [],

            }
        },
        computed: {
            config: () => window.authoredConfig,
            // The total pages to show for pagination buttons
            pageCount: function () {
                return Math.ceil(this.filteredArticles.length / this.limit);
            },

            // The current set of articles showing for the page
            currentArticles: function() {
                
                if (!this.filteredArticles.length) {
                    return [];
                }

                debugger;

                var offset = (this.page-1) * this.limit;
                var total = offset + this.limit;
                

                return this.filteredArticles.slice(offset, total);
            }
        },
        mounted: function () {

            var vue = this;

            // Find some articles
            axios.get(this.config.route).then(function(response) {
                
                // Convert response into an array
                var articles = Object.keys(response.data).map(function(key) {
                    return response.data[key];
                });

                vue.allArticles = articles;
                vue.filteredArticles = articles;
                
                vue.statuses = {
                    'All': 0,
                    'Drafted': 0,
                    'Awaiting Moderation': 0,
                    'Rejected': 0,
                    'Awaiting Publication': 0,
                    'Published': 0,
                    'Archived': 0
                };

                articles.forEach(function (article) {
                    vue.statuses['All']++;
                    vue.statuses[article.status]++;
                });

                // In the case that this page has loaded with a page and/or tab in the URL params - update the page to reflect those params
                if (vue.tabSelected !== 'All') {
                    vue.onTabChange(vue.tabSelected, true, true);
                }
                if (vue.page > 1) {
                    vue.onPaginate(vue.page, true);
                }

                // Reinit foundation for the kebab dropdowns, for some reason this has to happen just very slightly delayed :|
                // setTimeout(function(){
                //     $(document).foundation();
                // }, 1);

                vue.articlesLoading = false;

            }).catch(function (error) {
                console.log(error);
            });

            // Listen for window state change
            window.onpopstate = function(event) {
                if (!event.state) {
                    // Return to defaults
                    vue.page = 1;
                    vue.onPaginate(vue.page, true);
                    vue.tabSelected = 'All';
                    vue.onTabChange(vue.tabSelected, true, true);
                } else {
                    if (event.state.page) {
                        vue.page = event.state.page;
                        vue.onPaginate(event.state.page, true);
                    }

                    if (event.state.tab) {
                        vue.tabSelected = event.state.tab;
                        vue.onTabChange(event.state.tab, true, true);
                    }
                }
            };

        },
        methods: {
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
                    if (this.tabSelected === 'All') {
                        params = '?p=' + page;
                    } else {
                        params = '?p=' + page + '&status=' + this.tabSelected;
                    }
                    window.history.pushState({page: page, tab: this.tabSelected}, '', params);
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
                var vue = this;

                vue.tabSelected = tab;
                if (!persistPage) {
                    vue.page = 1;
                }

                if (tab === 'All') {
                    // Reset back to original set
                    vue.filteredArticles = vue.allArticles;
                } else {
                    // Filter by tab selected
                    var filteredArticles = vue.allArticles.filter(function (article) {
                        return article.status === tab;
                    });

                    vue.filteredArticles = filteredArticles;
                }

                // Update the window history
                if (!holdState) {
                    window.history.pushState({tab: tab, page: vue.page}, '', '?status=' + tab);
                }
            }
        },

    }
</script>
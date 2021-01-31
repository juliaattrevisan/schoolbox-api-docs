<template>
    <div>
        <section>
            <dl v-if="isArticlesLoaded" class="tabs" :id="topicContainerId">
                <dd v-for="(statusCount, status) in statuses" :class="{active: tabSelected === status}" :key="status">
                    <a v-if="statusCount" @click="onTabChange(status)">{{ status }}</a>
                </dd>
            </dl>
            <dl v-else class="tabs" :id="topicContainerId">
                <dd class="skeleton" v-for="(n, index) in 3" :key="index">
                    <a></a>
                </dd>
            </dl>
            <div class="tabs-content no-margin">
                <ul v-if="isArticlesLoaded" class="information-list">
                    <li class="actions-small-1" v-for="article in currentArticles" :key="article.id">
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
                            <a v-if="article._links.update.auth" :href="article._links.update.href" class="icon-modify no-text" :title="'Modify this ' + localeNews"></a>
                        </nav>
                    </li>
                </ul>
                <ul v-else class="information-list">
                    <li class="actions-small-1" v-for="(n, index) in 3" :key="index">
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
                    <p v-if="canCreateNews"><a :href="createNewsRoute">Create {{ localeNews }}</a>.</p>
                </div>
            </div><!-- <div class="tabs-content no-margin"> -->
        </section>
        <paginate
            v-model="activePageNumber"
            :page-count="pageCount"
            :container-class="'pagination'"
            :prev-text="'‹'"
            :next-text="'›'"
            :prev-link-class="'arrow'"
            :next-link-class="'arrow'"
            :disabled-class="'unavailable'"
            :click-handler="onPaginate"
            v-if="currentArticles.length && pageCount > 1"
        >
        </paginate>
    </div>
</template>

<script>
    
    // import VuejsPaginate from 'vuejs-paginate';
    
    export default {
        components: {
            'paginate': VuejsPaginate
        },
        props: {
            activePageNumber: Number, // The active page on the pagination
            tabSelected: String, // The selected tab, just used for active styling
            newsRoute: String,
            limitPerPage: Number,
            topicContainerId: Number,
            localeNews: String,
            localeNewsPlural: String,
            canCreateNews: Boolean,
            createNewsRoute: String,
        },
        data: function() {
            return {
                isArticlesLoaded: false,
                statuses: {}, // A unique list of statuses returned from the user's articles, Used to display tab options
                allArticles: [],
                filteredArticles: []  // A container for a filtered list of authored articles, Will be populated as all articles on load
            }
        },
        computed: {
            
            // The total pages to show for pagination buttons
            pageCount: function () {
                return Math.ceil(this.filteredArticles.length / this.limitPerPage);
            },

            // The current set of articles showing for the page
            currentArticles: function() {
                
                if (this.filteredArticles.length === 0) {
                    return [];
                }

                let offset = (this.activePageNumber-1) * this.limitPerPage;
                let total = offset + this.limitPerPage;
                
                return this.filteredArticles.slice(offset, total);

            }
        },
        mounted: function () {

            fetch(this.newsRoute)
            .then((response) => response.json())
            .then((response) => {

                this.allArticles = response;
                this.filteredArticles = response;

                this.statuses = {
                    'All': 0,
                    'Drafted': 0,
                    'Awaiting Moderation': 0,
                    'Rejected': 0,
                    'Awaiting Publication': 0,
                    'Published': 0,
                    'Archived': 0
                };

                response.forEach((article) => {
                    this.statuses['All']++;
                    this.statuses[article.status]++;
                });

                // In the case that this page has loaded with a page and/or tab in the URL params - update the page to reflect those params
                if (this.tabSelected !== 'All') {
                    this.onTabChange(this.tabSelected, true, true);
                }
                if (this.activePageNumber > 1) {
                    this.onPaginate(this.activePageNumber, true);
                }

                // Reinit foundation for the kebab dropdowns, for some reason this has to happen just very slightly delayed :|
                setTimeout(function(){
                    $(document).foundation();
                }, 1);
                
                this.isArticlesLoaded = true;

            }).catch((error) => {
                console.log(error);
            });

            // Listen for window state change
            window.onpopstate = (event) => {
                if (!event.state) {
                    // Return to defaults
                    this.page = 1;
                    this.onPaginate(this.page, true);
                    this.tabSelected = 'All';
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
            }

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

                this.tabSelected = tab;

                if (!persistPage) {
                    this.activePageNumber = 1;
                }

                if (tab === 'All') {
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
                    window.history.pushState({ tab: tab, page: this.activePageNumber }, '', '?status=' + tab);
                
                }
            }
        },

    }
</script>
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
            

        </section>
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

                vue.articlesLoading = false;
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
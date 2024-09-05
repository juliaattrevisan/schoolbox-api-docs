<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Provides a link for RSS feeds
 * Changes depending on the current news feed tab selection
 *
 */

export default {
    props: {
        route: String,
        initialQueryStringTopic: String,
        initialTopic: String,
    },

    data() {
        return {
            queryStringTopic: "",
            topic: "",
        };
    },

    computed: {
        /**
         * the rss link, that changes on tab selection changes
         *
         * @returns {String} eg: https://vagrant.dev.schoolbox.cloud/news/feed/baf8da85532750735975e1809?topic=academic
         *
         */
        link() {
            return this.route + "?" + this.queryStringTopic + "=" + this.topic;
        },
    },

    mounted() {
        this.initData();
        this.initTabChangeListener();
    },

    methods: {
        /**
         * those that will change
         *
         */
        initData() {
            this.queryStringTopic = this.initialQueryStringTopic;
            this.topic = this.initialTopic;
        },

        /**
         * Vue can't track data that is changed from a completely disconnected Vue component
         * So #article-list-mixin dipatches the event so the link can change
         *
         */
        initTabChangeListener() {
            document.addEventListener("article-list-mixin__onTabChange", (event) => {
                this.queryStringTopic = event.detail.queryStringTopic;
                this.topic = event.detail.tab;
            });
        },
    },
};
</script>

<template>
    <a :href="link" class="icon-rss" title="Download RSS feed for this topic" id="rss"></a>
</template>

<script>

/**
 * Provides a link for RSS feeds
 * Changes depending on the current news feed tab selection
 *
 */

export default {
    
    props: {
        route: String,
        initialQueryStringTopic: String,
        initialTopic: String
    },

    data () {
        return {
            queryStringTopic: '',
            topic: ''
        }
    },

    computed: {
        
        link () {
            return this.route + '?' + this.queryStringTopic + '=' + this.topic;
        }

    },

    mounted () {
        
        this.queryStringTopic = this.initialQueryStringTopic;
        this.topic = this.initialTopic;

        document.addEventListener('article-list-mixin__onTabChange', (event) => {
            this.queryStringTopic = event.detail.queryStringTopic;
            this.topic = event.detail.tab;
        });  

    }

}
</script>

<template>
    <a :href="link" class='icon-rss' title="Download RSS feed for this topic" id="rss"></a>
</template>


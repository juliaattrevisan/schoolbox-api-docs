<script>

/**
 * Provides a link for RSS feeds
 * Changes depending on the current news feed tab selection
 *
 */

export default {
    
    props: {
        route: String,
        queryStringTopic: String,
        topic: String
    },

    computed: {
        
        link () {
            return this.route + '?' + this.queryStringTopic + '=' + this.topic;
        }

    },

    mounted () {

        document.addEventListener('article-list-mixin__onTabChange', (event) => {
            console.log('oi');
        });  
        
    }

}
</script>

<template>
    <a :href="link" class='icon-rss' title="Download RSS feed for this topic" id="rss"></a>
</template>


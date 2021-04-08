<script>
export default {
    
    props: {
        route: String,
        queryStringTopic: String,
        topic: String
    },

    computed: {
        
        link () {
            return this.route + '?' + this.queryStringTopic + '=' + this.topic
        }
        
    }

}
</script>

<template>
    <a :href="link" class='icon-rss' title="Download RSS feed for this topic" id="rss"></a>
</template>


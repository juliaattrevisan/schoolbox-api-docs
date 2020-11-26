import Vue from 'vue';
import AwardFeed from "./award-feed.vue";

new Vue({
    el: '#award-feed',
    render: function (createElement) {
        return createElement(AwardFeed)
    }
});

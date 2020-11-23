import Vue from 'vue';
import AwardFeed from "./badge-award-feed.vue";

new Vue({
    el: '#badge-award-feed',
    render: function (createElement) {
        return createElement(AwardFeed)
    }
});

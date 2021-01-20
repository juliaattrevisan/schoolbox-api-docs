import Vue from "vue";
import Feed from './feed.vue';

window.activityFeed = new Vue({
    el: '#activity-feed',
    render: function (createElement) {
        return createElement(Feed)
    }
});

import Vue from 'vue';
import GoalFeed from "./goal-feed.vue";

new Vue({
    el: '#goal-feed',
    render: function (createElement) {
        return createElement(GoalFeed)
    }
});

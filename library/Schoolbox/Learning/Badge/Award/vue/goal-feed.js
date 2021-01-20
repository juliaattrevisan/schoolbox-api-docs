import Vue from 'vue';
import GoalFeed from "./goal-feed.vue";

window.goalFeed = new Vue({
    el: '#goal-feed',
    render: function (createElement) {
        return createElement(GoalFeed)
    }
});

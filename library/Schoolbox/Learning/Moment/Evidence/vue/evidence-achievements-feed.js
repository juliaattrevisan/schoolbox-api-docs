import Vue from 'vue';
import AchievementsFeed from "./evidence-achievements-feed.vue";

new Vue({
    el: '#evidence-achievements-feed',
    render: function (createElement) {
        return createElement(AchievementsFeed)
    }
});

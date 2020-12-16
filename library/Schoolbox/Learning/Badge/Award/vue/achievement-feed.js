import Vue from 'vue';
import AchievementFeed from "./achievement-feed.vue";

new Vue({
    el: '#achievement-feed',
    render: function (createElement) {
        return createElement(AchievementFeed)
    }
});

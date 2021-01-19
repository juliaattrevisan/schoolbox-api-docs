import Vue from 'vue';
import AchievementFeed from "./achievement-feed.vue";

window.achievementFeed = new Vue({
    el: '#achievement-feed',
    render: function (createElement) {
        return createElement(AchievementFeed)
    }
});

import Vue from "vue";
import ActivityFeed from '../../Moment/Evidence/vue/feed.vue';
import GoalFeed from "../../Badge/Award/vue/goal-feed.vue";
import AchievementFeed from "../../Badge/Award/vue/achievement-feed.vue";

window.activityFeed = new Vue({
    el: '#activity-feed',
    components: {
        'activity-feed': ActivityFeed
    }
});

window.goalFeed = new Vue({
    el: '#goal-feed',
    components: {
        'goal-feed': GoalFeed
    }
});

window.achievementFeed = new Vue({
    el: '#achievement-feed',
    components: {
        'achievement-feed': AchievementFeed
    }
});

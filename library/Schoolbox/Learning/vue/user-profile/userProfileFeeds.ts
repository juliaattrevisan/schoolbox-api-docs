import ActivityFeed from "../../Moment/Evidence/vue/activity-feed.vue";
import GoalFeed from "../../Badge/Award/vue/goal-feed.vue";
import AchievementFeed from "../../Badge/Award/vue/achievement-feed.vue";

window.Vue.createApp({
    components: {
        ActivityFeed,
    },
}).mount("#activity-feed");

window.Vue.createApp({
    components: {
        GoalFeed,
    },
}).mount("#goal-feed");

window.Vue.createApp({
    components: {
        AchievementFeed,
    },
}).mount("#achievement-feed");

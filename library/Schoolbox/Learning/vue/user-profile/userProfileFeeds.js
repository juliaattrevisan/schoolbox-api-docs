import { createApp } from "vue";
import ActivityFeed from "../../Moment/Evidence/vue/feed";
import GoalFeed from "../../Badge/Award/vue/goal-feed";
import AchievementFeed from "../../Badge/Award/vue/achievement-feed";

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

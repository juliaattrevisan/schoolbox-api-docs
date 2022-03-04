import { createApp } from 'vue';
import ActivityFeed from '../../Moment/Evidence/vue/feed';
import GoalFeed from '../../Badge/Award/vue/goal-feed';
import AchievementFeed from '../../Badge/Award/vue/achievement-feed';

createApp({
    components: {
        ActivityFeed
    }
}).mount('#activity-feed');

createApp({
    components: {
        GoalFeed
    }
}).mount('#goal-feed');

createApp({
    components: {
        AchievementFeed
    }
}).mount('#achievement-feed');
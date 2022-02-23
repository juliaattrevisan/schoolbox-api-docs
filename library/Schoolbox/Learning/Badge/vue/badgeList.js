import { createApp } from 'vue';
import BadgeList from './badge-list';

window.badgeList = createApp({
    components: {
        BadgeList
    }
}).mount('#badge-list-container');
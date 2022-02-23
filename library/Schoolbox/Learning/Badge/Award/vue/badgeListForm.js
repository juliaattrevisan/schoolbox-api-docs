import { createApp } from 'vue';
import BadgeListForm from './badge-list-form';

window.badgeList = createApp({
    components: {
        BadgeListForm
    }
}).mount('#badge-list-form-container');
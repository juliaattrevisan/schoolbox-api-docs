import Vue from 'vue';
import BadgeReport from './badge-report';
import { ClientTable } from 'vue-tables-2';

Vue.use(ClientTable);

new Vue({
    el: '#badge-report-container',
    components: {
        BadgeReport
    }
});

import Vue from 'vue';
import VisitationListFull from './visitation-list-full';
import { ClientTable } from 'vue-tables-2-premium';

Vue.use(ClientTable);

new Vue({
    el: '#visitation-list-full-container',
    components: {
        'visitation-list-full': VisitationListFull
    }
});

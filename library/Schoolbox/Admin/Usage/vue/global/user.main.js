import Vue from 'vue';
import { ClientTable } from 'vue-tables-2';
import UsageGlobalUser from './user';

Vue.use(ClientTable);

new Vue({
    el: '#usage-global-user-container',
    components: {
        'usage-global-user': UsageGlobalUser
    }
});
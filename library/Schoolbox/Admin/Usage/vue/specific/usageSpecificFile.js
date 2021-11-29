import Vue from 'vue';
import { ClientTable } from 'vue-tables-2-premium';
import UsageSpecificFile from './file';

Vue.use(ClientTable);

new Vue({
    el: '#usage-specific-file-container',
    components: {
        'usage-specific-file': UsageSpecificFile
    }
});
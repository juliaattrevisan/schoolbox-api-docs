import Vue from 'vue';
import { ClientTable } from 'vue-tables-2-premium';
import ViewAllFiles from './view-all-files';

Vue.use(ClientTable);

new Vue({
    el: '#view-all-files-container',
    components: {
        ViewAllFiles
    }
});
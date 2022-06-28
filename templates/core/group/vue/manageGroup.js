import { createApp } from 'vue';
import ManageGroup from './manage-group';
import { ClientTable } from 'v-tables-3-select-rows';

createApp({
    components: {
        ManageGroup
    }
}).use(ClientTable).mount('#manage-group-container');
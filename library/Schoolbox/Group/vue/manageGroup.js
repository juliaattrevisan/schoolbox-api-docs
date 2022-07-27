import { createApp } from 'vue';
import ManageGroup from './manage-group';
import { ClientTable } from 'v-tables-3-select-rows';
import { createPinia } from 'pinia';

createApp({
    components: {
        ManageGroup
    }
}).use(ClientTable).use(createPinia()).mount('#manage-group-container');


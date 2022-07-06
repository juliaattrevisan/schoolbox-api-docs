import { createApp } from 'vue';
import ShapsList from './shaps-list';
import { ClientTable } from 'v-tables-3-select-rows';

createApp({
    components: {
        ShapsList
    }
}).use(ClientTable).mount('#shaps-list-container');

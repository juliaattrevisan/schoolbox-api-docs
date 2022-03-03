import { createApp } from 'vue';
import UsageGlobal from './usage-global';
import { ClientTable } from 'v-tables-3-select-rows';

createApp({
    components: {
        UsageGlobal
    }
}).use(ClientTable).mount('#usage-global-container'); 
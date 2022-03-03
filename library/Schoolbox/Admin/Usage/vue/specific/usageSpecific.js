import { createApp } from 'vue';
import UsageSpecific from './usage-specific';
import { ClientTable } from 'v-tables-3-select-rows';

createApp({
    components: {
        UsageSpecific
    }
}).use(ClientTable).mount('#usage-specific-container'); 
import { createApp } from 'vue';
import EmailDkim from './email-dkim';
import { ClientTable } from 'v-tables-3-select-rows';

createApp({
    components: {
        EmailDkim
    }
}).use(ClientTable).mount('#email-dkim-container');

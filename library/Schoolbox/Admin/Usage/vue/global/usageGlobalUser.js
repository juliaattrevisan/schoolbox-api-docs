import { createApp } from 'vue';
import UsageGlobalUser from './user';
import { ClientTable } from 'v-tables-3-select-rows';

const app = createApp({
    components: {
        UsageGlobalUser
    }
});
app.use(ClientTable)
app.mount('#usage-global-user-container');

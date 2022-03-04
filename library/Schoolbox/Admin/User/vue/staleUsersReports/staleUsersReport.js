import { createApp } from 'vue';
import StaleUsersReport from './stale-users-report';
import { ClientTable } from 'v-tables-3-select-rows';

const app = createApp({
    components: {
        StaleUsersReport
    }
});
app.use(ClientTable)
app.mount('#stale-users-report');

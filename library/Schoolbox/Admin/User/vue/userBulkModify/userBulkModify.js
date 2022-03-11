import { createApp } from 'vue';
import UserBulkModify from './user-bulk-modify';
import { ClientTable } from 'v-tables-3-select-rows';

const app = createApp({
    components: {
        UserBulkModify
    }
});
app.use(ClientTable)
app.mount('#user-bulk-modify');

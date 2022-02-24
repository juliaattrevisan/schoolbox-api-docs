import { createApp } from 'vue';
import UsageSpecificFile from './file';
import { ClientTable } from 'v-tables-3-select-rows';

const app = createApp({
    components: {
        UsageSpecificFile
    }
});
app.use(ClientTable)
app.mount('#usage-specific-file-container');

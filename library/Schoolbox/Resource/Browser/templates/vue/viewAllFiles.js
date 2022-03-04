import { createApp } from 'vue';
import ViewAllFiles from './view-all-files';
import { ClientTable } from 'v-tables-3-select-rows';

const app = createApp({
    components: {
        ViewAllFiles
    }
});
app.use(ClientTable)
app.mount('#view-all-files-container');
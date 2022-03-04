import { createApp } from 'vue';
import VisitationListFull from './visitation-list-full';
import { ClientTable } from 'v-tables-3-select-rows';

const app = createApp({
    components: {
        VisitationListFull
    }
});
app.use(ClientTable)
app.mount('#visitation-list-full-container');

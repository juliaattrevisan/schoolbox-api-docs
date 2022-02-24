import { createApp } from 'vue';
import VisitationListFull from './visitation-list-full';
import { ClientTable } from 'vue-tables-2-premium';

const app = createApp({
    components: {
        VisitationListFull
    }
});
app.use(ClientTable)
app.mount('#visitation-list-full-container');

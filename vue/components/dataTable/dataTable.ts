/**
 * To mount the Vue app to display the staff directory table
 */

import { ClientTable } from "v-tables-3-select-rows";
import DataTable from "./data-table.vue";

const app = window.Vue.createApp({
    components: {
        DataTable,
    },
});
app.use(ClientTable);
app.mount("#data-table-container");

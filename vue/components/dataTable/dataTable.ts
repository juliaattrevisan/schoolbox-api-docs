/**
 * To mount the Vue app to display the staff directory table
 */

import { ClientTable } from "v-tables-3-select-rows";
import DataTable from "./data-table";

const app = window.Vue.createApp({
    components: {
        DataTable,
    },
});
app.use(ClientTable);
app.mount("#data-table-container");

/**
 * To allow schools to alter whats displayed in the columns
 */
export interface ColumnsConfig {
    heading: string; // the heading of each column
    data: string; // the id of the data to display in each column
    class: string; // to allow any custom styling, such as hiding a column on smaller screens
}

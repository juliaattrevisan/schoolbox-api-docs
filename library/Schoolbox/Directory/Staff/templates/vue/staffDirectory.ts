/**
 * To mount the Vue app to display the staff directory table
 */

import { ClientTable } from "v-tables-3-select-rows";
import StaffDirectory from "./staff-directory";

const app = window.Vue.createApp({
    components: {
        StaffDirectory,
    },
});
app.use(ClientTable);
app.mount("#staff-directory-container");

/*
 * To allow schools to alter whats displayed in the columns
 */
export interface ColumnsConfig {
    heading: string; // the heading of the column
    data: string; // the id of the data to display in the cell
    class: string; // to allow any custom styling, such as hiding a column on smaller screens
}

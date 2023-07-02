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

/**
 * To allow schools to alter whats displayed in the columns
 */
export interface ColumnsConfig {
    heading: string; // the heading of each column
    data: string; // the id of the data to display in each column
    class: string; // to allow any custom styling, such as hiding a column on smaller screens
}

/**
 * The default data that is displayed in each column
 */
export interface ColumnsData {
    portrait_url: string;
    profile_url: string;
    StaffPreferredName: string;
    StaffSurname: string;
    StaffDepartment: string;
    JobPositionDescription: string;
    StaffCampusDescription: string;
    StaffOccupPhone: string;
    StaffExtension: string;
    StaffOccupEmail: string;
}

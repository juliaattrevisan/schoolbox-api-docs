import StaffDirectory from "./staff-directory";
import { ClientTable } from "v-tables-3-select-rows";

const app = window.Vue.createApp({
    components: {
        StaffDirectory,
    },
});
app.use(ClientTable);
app.mount("#staff-directory-container");

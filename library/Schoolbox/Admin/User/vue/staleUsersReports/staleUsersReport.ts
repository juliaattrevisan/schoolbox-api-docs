import { ClientTable } from "v-tables-3-select-rows";
import StaleUsersReport from "./stale-users-report.vue";

const app = window.Vue.createApp({
    components: {
        StaleUsersReport,
    },
});
app.use(ClientTable);
app.mount("#stale-users-report");

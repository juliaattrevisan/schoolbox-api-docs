import { ClientTable } from "v-tables-3-select-rows";
import UserBulkModify from "./user-bulk-modify.vue";

window.Vue.createApp({
    components: {
        UserBulkModify,
    },
})
    .use(ClientTable)
    .mount("#user-bulk-modify-container");

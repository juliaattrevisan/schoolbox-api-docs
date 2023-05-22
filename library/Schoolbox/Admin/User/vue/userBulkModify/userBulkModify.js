import { createApp } from "vue";
import UserBulkModify from "./user-bulk-modify";
import { ClientTable } from "v-tables-3-select-rows";

window.Vue.createApp({
    components: {
        UserBulkModify,
    },
})
    .use(ClientTable)
    .mount("#user-bulk-modify-container");

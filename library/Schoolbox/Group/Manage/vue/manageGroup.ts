import { ClientTable } from "v-tables-3-select-rows";
import { createPinia } from "pinia";
import ManageGroup from "./manage-group.vue";

window.Vue.createApp({
    components: {
        ManageGroup,
    },
})
    .use(ClientTable)
    .use(createPinia())
    .mount("#manage-group-container");

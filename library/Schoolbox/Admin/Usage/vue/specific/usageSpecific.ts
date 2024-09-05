import { ClientTable } from "v-tables-3-select-rows";
import UsageSpecific from "./usage-specific.vue";

window.Vue.createApp({
    components: {
        UsageSpecific,
    },
})
    .use(ClientTable)
    .mount("#usage-specific-container");

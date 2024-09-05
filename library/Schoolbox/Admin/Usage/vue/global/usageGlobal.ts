import { ClientTable } from "v-tables-3-select-rows";
import UsageGlobal from "./usage-global.vue";

window.Vue.createApp({
    components: {
        UsageGlobal,
    },
})
    .use(ClientTable)
    .mount("#usage-global-container");

import { ClientTable } from "v-tables-3-select-rows";
import EmailDkim from "./email-dkim.vue";

window.Vue.createApp({
    components: {
        EmailDkim,
    },
})
    .use(ClientTable)
    .mount("#email-dkim-container");

import { createPinia } from "pinia";
import DialogAgentConfigure from "./dialog-agent-configure.vue";

window.Vue.createApp({
    components: {
        DialogAgentConfigure,
    },
})
    .use(createPinia())
    .mount("#dialog-agent-configure-container");

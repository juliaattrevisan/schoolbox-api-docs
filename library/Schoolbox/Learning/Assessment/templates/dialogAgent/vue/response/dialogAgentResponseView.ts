import { createPinia } from "pinia";
import DialogAgentResponseView from "./dialog-agent-response-view.vue";

window.Vue.createApp({
    components: {
        DialogAgentResponseView,
    },
})
    .use(createPinia())
    .mount("#dialog-agent-response-view-container");

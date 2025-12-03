import { createPinia } from "pinia";
import DialogAgentResponseView from "./dialog-agent-response-view.vue";
import DialogAgentResponseChat from "./dialog-agent-response-chat.vue";

window.Vue.createApp({
    components: {
        DialogAgentResponseChat,
        DialogAgentResponseView,
    },
})
    .use(createPinia())
    .mount("#dialog-agent-response-container");

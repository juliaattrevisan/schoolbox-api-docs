import { createPinia } from "pinia";
import DialogAgentChat from "./dialog-agent-chat.vue";

window.Vue.createApp({
    components: {
        DialogAgentChat,
    },
})
    .use(createPinia())
    .mount("#dialog-agent-chat-container");

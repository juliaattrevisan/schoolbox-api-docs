import { createPinia } from "pinia";
import AiUsageLog from "./ai-usage-log.vue";

window.Vue.createApp({
    components: {
        AiUsageLog,
    },
})
    .use(createPinia())
    .mount("#ai-usage-log-container");

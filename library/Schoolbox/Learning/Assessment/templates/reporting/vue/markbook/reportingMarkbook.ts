import { createPinia } from "pinia";
import ReportingMarkbook from "./reporting-markbook.vue";

window.Vue.createApp({
    components: {
        ReportingMarkbook,
    },
})
    .use(createPinia())
    .mount("#reporting-markbook-container");

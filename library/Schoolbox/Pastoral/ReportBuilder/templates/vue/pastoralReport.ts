import { createPinia } from "pinia";
import PastoralReport from "./pastoral-report.vue";

window.Vue.createApp({
    components: {
        PastoralReport,
    },
})
    .use(createPinia())
    .mount("#pastoral-report-container");

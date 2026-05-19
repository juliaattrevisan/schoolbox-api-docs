import { createPinia } from "pinia";
import IbReport from "./ib-report.vue";

window.Vue.createApp({
    components: {
        IbReport,
    },
})
    .use(createPinia())
    .mount("#ib-report-app");

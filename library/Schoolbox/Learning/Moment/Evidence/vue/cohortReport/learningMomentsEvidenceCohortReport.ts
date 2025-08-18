import { createPinia } from "pinia";
import CohortReport from "./cohort-report.vue";

window.Vue.createApp({
    components: {
        CohortReport,
    },
})
    .use(createPinia())
    .mount("#learning-moments-evidence-cohort-report-container");

import { createPinia } from "pinia";
import CurriculumCoverage from "./curriculum-coverage.vue";

window.Vue.createApp({
    components: {
        CurriculumCoverage,
    },
})
    .use(createPinia())
    .mount("#curriculum-coverage-container");

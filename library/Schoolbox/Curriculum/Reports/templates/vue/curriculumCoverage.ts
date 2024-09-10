import { createPinia } from "pinia";
import CurriculumCoverage from "./curriculum-coverage.vue";

window.Vue.createApp({
    components: {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        CurriculumCoverage,
    },
})
    .use(createPinia())
    .mount("#curriculum-coverage-container");

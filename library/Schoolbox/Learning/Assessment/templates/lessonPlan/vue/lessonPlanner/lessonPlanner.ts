import { createPinia } from "pinia";
import CurriculumSelector from "@schoolboxCurriculum/vue/curriculumSelector/curriculum-selector.vue";
import LessonPlanner from "./lesson-planner.vue";

window.Vue.createApp({
    components: {
        CurriculumSelector,
        LessonPlanner,
    },
})
    .use(createPinia())
    .mount("#lesson-planner-container");

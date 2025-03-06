import { createPinia } from "pinia";
import LessonPlanner from "./lesson-planner.vue";

window.Vue.createApp({
    components: {
        LessonPlanner,
    },
})
    .use(createPinia())
    .mount("#lesson-planner-container");

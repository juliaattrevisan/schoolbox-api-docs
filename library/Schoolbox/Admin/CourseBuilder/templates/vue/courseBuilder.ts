import { createPinia } from "pinia";
import CourseBuilder from "./course-builder.vue";

window.Vue.createApp({
    components: {
        CourseBuilder,
    },
})
    .use(createPinia())
    .mount("#course-builder-container");

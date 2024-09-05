import { createPinia } from "pinia";
import ManageCurriculum from "./manage-curriculum.vue";

window.Vue.createApp({
    components: {
        ManageCurriculum,
    },
})
    .use(createPinia())
    .mount("#manage-curriculum-container");

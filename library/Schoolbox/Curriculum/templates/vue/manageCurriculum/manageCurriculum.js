import ManageCurriculum from "./manage-curriculum";
import { createPinia } from "pinia";
window.Vue.createApp({
    components: {
        ManageCurriculum,
    },
})
    .use(createPinia())
    .mount("#manage-curriculum-container");

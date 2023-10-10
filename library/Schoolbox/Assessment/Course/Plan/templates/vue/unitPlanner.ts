import { createPinia } from "pinia";
import UnitPlanner from "./unit-planner.vue";

window.Vue.createApp({
    components: {
        UnitPlanner,
    },
})
    .use(createPinia())
    .mount("#unit-planner-container");

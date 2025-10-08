import { createPinia } from "pinia";
import HorizontalMarking from "./horizontal-marking.vue";

window.Vue.createApp({
    components: {
        HorizontalMarking,
    },
})
    .use(createPinia())
    .mount("#horizontal-marking-container");

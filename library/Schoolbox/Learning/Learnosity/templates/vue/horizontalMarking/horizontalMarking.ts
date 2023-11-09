import { createPinia } from "pinia";
import HorizontalMarking from "./horizontal-marking.vue";

window.Vue.createApp({
    components: {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        HorizontalMarking,
    },
})
    .use(createPinia())
    .mount("#horizontal-marking-container");

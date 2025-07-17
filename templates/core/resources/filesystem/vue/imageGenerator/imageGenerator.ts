import { createPinia } from "pinia";
import ImageGenerator from "./image-generator.vue";

window.Vue.createApp({
    components: {
        ImageGenerator,
    },
})
    .use(createPinia())
    .mount("#image-generator-container");

import { createPinia } from "pinia";
import PwaMenu from "./pwa-menu.vue";

// Share the same store across all the pwa components so we don't have to do event schenanigans
const pinia = createPinia();

document.querySelectorAll('[data-vue-app="pwa-menu"]').forEach((el) => {
    window.Vue.createApp({
        components: {
            PwaMenu,
        },
    })
        .use(pinia)
        .mount(el);
});

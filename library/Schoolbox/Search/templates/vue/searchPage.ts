import { createPinia } from "pinia";
import SearchPage from "./search-page.vue";

window.Vue.createApp({
    components: {
        SearchPage,
    },
})
    .use(createPinia())
    .mount("#search-page-container");

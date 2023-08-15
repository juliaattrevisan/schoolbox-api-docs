import { createPinia } from "pinia";
import SearchPage from "./search-page.vue";

window.Vue.createApp({
    components: {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        SearchPage,
    },
})
    .use(createPinia())
    .mount("#search-page-container");

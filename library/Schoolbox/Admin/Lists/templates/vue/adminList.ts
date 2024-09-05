import { createPinia } from "pinia";
import AdminList from "./admin-list.vue";

window.Vue.createApp({
    components: {
        AdminList,
    },
})
    .use(createPinia())
    .mount("#admin-list");

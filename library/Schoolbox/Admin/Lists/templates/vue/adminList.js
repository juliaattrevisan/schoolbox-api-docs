import { createApp } from "vue";
import { createPinia } from "pinia";
import AdminList from "./admin-list";

window.Vue.createApp({
    components: {
        AdminList,
    },
})
    .use(createPinia())
    .mount("#admin-list");

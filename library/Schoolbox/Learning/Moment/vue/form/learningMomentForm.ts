import { createPinia } from "pinia";
import MomentForm from "./moment-form.vue";

window.Vue.createApp({
    components: {
        MomentForm,
    },
})
    .use(createPinia())
    .mount("#learning-moment-form-container");

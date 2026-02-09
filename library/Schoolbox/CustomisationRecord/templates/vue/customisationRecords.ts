import { createPinia } from "pinia";
import CustomisationRecords from "./customisation-records.vue";

window.Vue.createApp({
    components: {
        CustomisationRecords,
    },
})
    .use(createPinia())
    .mount("#customisation-records-container");

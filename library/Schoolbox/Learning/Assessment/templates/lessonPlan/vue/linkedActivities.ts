import { createPinia } from "pinia";
import LinkedActivities from "./linked-activities";

window.Vue.createApp({
    components: {
        LinkedActivities,
    },
})
    .use(createPinia())
    .mount("#linked-activities-container");

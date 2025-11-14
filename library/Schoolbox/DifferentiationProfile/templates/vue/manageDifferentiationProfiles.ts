import { createPinia } from "pinia";
import ManageDifferentiationProfiles from "./manage-differentiation-profiles.vue";

window.Vue.createApp({
    components: {
        ManageDifferentiationProfiles,
    },
})
    .use(createPinia())
    .mount("#manage-differentiation-profiles-container");

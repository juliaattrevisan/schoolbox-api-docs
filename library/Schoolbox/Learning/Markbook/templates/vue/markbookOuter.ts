/**
 * Used for both the Course Markbook and the Class Markbook
 */

import { createPinia } from "pinia";
import MarkbookOuter from "./markbook-outer.vue";

window.Vue.createApp({
    components: {
        MarkbookOuter,
    },
})
    .use(createPinia())
    .mount("#markbook-outer-container");

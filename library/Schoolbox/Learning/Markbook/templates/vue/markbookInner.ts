/**
 * Used for both the Course Markbook and the Class Markbook
 */

import { createPinia } from "pinia";
import MarkbookInner from "./markbook-inner.vue";

window.Vue.createApp({
    components: {
        MarkbookInner,
    },
})
    .use(createPinia())
    .mount("#markbook-inner-container");

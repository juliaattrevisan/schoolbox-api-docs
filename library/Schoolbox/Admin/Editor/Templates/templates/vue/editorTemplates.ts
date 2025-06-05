import { createPinia } from "pinia";
import EditorTemplates from "./editor-templates.vue";

window.Vue.createApp({
    components: {
        EditorTemplates,
    },
})
    .use(createPinia())
    .mount("#editor-templates-container");

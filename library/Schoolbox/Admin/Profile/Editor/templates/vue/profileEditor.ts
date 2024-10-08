import { createPinia } from "pinia";
import ProfileEditor from "./profile-editor.vue";

window.Vue.createApp({
    components: {
        ProfileEditor,
    },
})
    .use(createPinia())
    .mount("#profile-editor-container");

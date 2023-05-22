import { createApp } from "vue";
import ClassSelector from "./class-selector";

window.Vue.createApp({
    components: {
        ClassSelector,
    },
}).mount("#class-selector-container");

import { createApp } from "vue";
import NewsAuthored from "./news-authored";

window.Vue.createApp({
    components: {
        NewsAuthored,
    },
}).mount("#authored-container");

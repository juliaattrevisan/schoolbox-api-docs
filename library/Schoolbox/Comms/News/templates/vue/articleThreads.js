import { createApp } from "vue";
import ArticleThreads from "./article-threads";

window.Vue.createApp({
    components: {
        ArticleThreads,
    },
}).mount("#threads-section");

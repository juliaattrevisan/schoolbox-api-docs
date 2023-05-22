import { createApp } from "vue";
import PastoralRecordComments from "./pastoral-record-comments";

window.Vue.createApp({
    components: {
        PastoralRecordComments,
    },
}).mount("#pastoral-record-comments-container-private");

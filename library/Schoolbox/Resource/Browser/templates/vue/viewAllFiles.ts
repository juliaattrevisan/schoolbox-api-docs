import { ClientTable } from "v-tables-3-select-rows";
import ViewAllFiles from "./view-all-files.vue";

const app = window.Vue.createApp({
    components: {
        ViewAllFiles,
    },
});
app.config.globalProperties.cdnPrefix = window.cdnPrefix;
app.use(ClientTable);
app.mount("#view-all-files-container");

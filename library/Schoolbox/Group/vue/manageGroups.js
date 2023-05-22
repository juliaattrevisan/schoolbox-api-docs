import { createApp } from "vue";
import ManageGroups from "./manage-groups";

window.Vue.createApp({
    components: {
        ManageGroups,
    },
}).mount("#manage-groups-container");

import { ClientTable } from "v-tables-3-select-rows";
import VisitationListFull from "./visitation-list-full.vue";

const app = window.Vue.createApp({
    components: {
        VisitationListFull,
    },
});
app.use(ClientTable);
app.mount("#visitation-list-full-container");

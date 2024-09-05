import { ClientTable } from "v-tables-3-select-rows";
import ShapsList from "./shaps-list.vue";

window.Vue.createApp({
    components: {
        ShapsList,
    },
})
    .use(ClientTable)
    .mount("#shaps-list-container");

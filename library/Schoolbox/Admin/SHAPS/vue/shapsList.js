import ShapsList from "./shaps-list";
import { ClientTable } from "v-tables-3-select-rows";

window.Vue.createApp({
    components: {
        ShapsList,
    },
})
    .use(ClientTable)
    .mount("#shaps-list-container");

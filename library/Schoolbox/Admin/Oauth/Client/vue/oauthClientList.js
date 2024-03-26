import { createPinia } from "pinia";
import OauthClientList from "./oauth-client-list";

window.Vue.createApp({
    components: {
        OauthClientList,
    },
})
    .use(createPinia())
    .mount("#oauth-client-list");

import { createApp } from "vue";
import ManageQrCodes from "./manage-qr-codes";

window.Vue.createApp({
    components: {
        ManageQrCodes,
    },
}).mount("#manage-qr-codes-container");

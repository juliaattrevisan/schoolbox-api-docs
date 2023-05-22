import { createApp } from "vue";
import SbFullCalendar from "./sb-full-calendar";

window.Vue.createApp({
    components: {
        SbFullCalendar,
    },
}).mount("#calendar");

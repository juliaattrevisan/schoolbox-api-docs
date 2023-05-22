import { createApp } from "vue";
import CalendarEventDetails from "./calendar-event-details";

window.Vue.createApp({
    components: {
        CalendarEventDetails,
    },
}).mount("#event-details");

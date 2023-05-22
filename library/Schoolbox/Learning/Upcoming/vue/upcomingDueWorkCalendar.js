import { createApp } from "vue";
import UpcomingDueWorkCalendar from "./upcoming-due-work-calendar";

window.Vue.createApp({
    components: {
        UpcomingDueWorkCalendar,
    },
}).mount("#calendar");

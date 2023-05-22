import { createApp } from "vue";
import TimetableCalendar from "./timetable-calendar";

window.Vue.createApp({
    components: {
        TimetableCalendar,
    },
}).mount("#timetable-calendar");

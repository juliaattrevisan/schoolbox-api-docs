import Vue from "vue";
import UpcomingDueWorkCalendar from "./upcoming-due-work-calendar";

window.upcomingDueworkCalendar = new Vue({
    el: '#upcoming-due-work-calendar',
    components: {
        'upcoming-due-work-calendar': UpcomingDueWorkCalendar
    }
});

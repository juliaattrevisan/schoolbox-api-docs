import { createApp } from 'vue';
import UpcomingDueWorkCalendar from './upcoming-due-work-calendar';

window.upcomingDueworkCalendar = createApp({
    components: {
        UpcomingDueWorkCalendar
    }
}).mount('#calendar');

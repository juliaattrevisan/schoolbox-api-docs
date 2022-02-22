import { createApp } from 'vue';
import AssessmentCalendar from './assessment-calendar';

window.assessmentCalendar = createApp({
    components: {
        AssessmentCalendar
    }
}).mount('#calendar');

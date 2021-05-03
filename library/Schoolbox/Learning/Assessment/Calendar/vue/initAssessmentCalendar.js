import Vue from "vue";
import AssessmentCalendar from "./assessment-calendar";

window.assessmentCalendar = new Vue({
    el: '#calendar',
    components: {
        'assessment-calendar': AssessmentCalendar
    }
});

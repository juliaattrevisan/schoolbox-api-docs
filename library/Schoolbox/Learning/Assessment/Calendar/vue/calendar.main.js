import Vue from "vue";
import FullCalendar from "./calendar.vue";

window.assessmentCalendar = new Vue({
    el: '#calendar',
    render: function(createElement) {
        return createElement(FullCalendar)
    }
});

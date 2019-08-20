import Vue from "vue";
import FullCalendar from "./calendar.vue";

new Vue({
    el: '#calendar',
    render: function(createElement) {
        return createElement(FullCalendar)
    }
});

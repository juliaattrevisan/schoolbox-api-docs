import Vue from "vue";
import FullCalendar from "./calendar";

window.resourceBookingICategoryCalendar = new Vue({
    el: '#calendar',
    render: function(createElement) {
        return createElement(FullCalendar)
    }
});

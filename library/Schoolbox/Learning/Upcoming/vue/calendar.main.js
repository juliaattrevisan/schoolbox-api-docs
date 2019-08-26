import Vue from "vue";
import FullCalendar from "./calendar";

window.upcomingDueworkCalendar = new Vue({
    el: '#calendar',
    render: function(createElement) {
        return createElement(FullCalendar)
    }
});

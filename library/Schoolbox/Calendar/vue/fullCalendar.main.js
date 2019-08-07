import Vue from "vue";
import FullCalendar from "./fullCalendar.vue";

new Vue({
    el: '#calendar',
    render: function(createElement) {
        return createElement(FullCalendar)
    }
});

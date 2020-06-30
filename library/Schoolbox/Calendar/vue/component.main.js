import Vue from "vue";
import FullCalendar from "./component.vue";

// Fortunately only one calendar may exist per dashboard or homepage
new Vue({
    el: '#calendar',
    render: function(createElement) {
        return createElement(FullCalendar)
    }
});

import Vue from "vue";
import CalendarComponent from "./calendar-component.vue";

// Fortunately only one calendar may exist per dashboard or homepage
new Vue({
    el: '#calendar',
    components: {
        'calendar-component': CalendarComponent
    }
});

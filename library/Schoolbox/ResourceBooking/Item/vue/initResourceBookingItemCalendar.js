import Vue from "vue";
import ResourceBookingItemCalendar from "./resource-booking-item-calendar";

window.resourceBookingItemCalendar = new Vue({
    el: '#calendar',
    components: {
        'resource-booking-item-calendar': ResourceBookingItemCalendar
    }
});

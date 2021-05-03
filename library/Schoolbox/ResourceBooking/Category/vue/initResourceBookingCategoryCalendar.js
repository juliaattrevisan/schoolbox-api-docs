import Vue from "vue";
import ResourceBookingCategoryCalendar from "./resource-booking-category-calendar";

window.resourceBookingICategoryCalendar = new Vue({
    el: '#calendar',
    components: {
        'resource-booking-category-calendar': ResourceBookingCategoryCalendar
    }
});

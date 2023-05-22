import { createApp } from "vue";
import ResourceBookingItemCalendar from "./resource-booking-item-calendar";

window.Vue.createApp({
    components: {
        ResourceBookingItemCalendar,
    },
}).mount("#calendar");

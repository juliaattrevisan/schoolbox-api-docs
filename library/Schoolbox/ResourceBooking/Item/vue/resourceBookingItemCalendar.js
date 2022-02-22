import { createApp } from 'vue';
import ResourceBookingItemCalendar from './resource-booking-item-calendar';

window.resourceBookingItemCalendar = createApp({
    components: {
        ResourceBookingItemCalendar
    }
}).mount('#calendar');

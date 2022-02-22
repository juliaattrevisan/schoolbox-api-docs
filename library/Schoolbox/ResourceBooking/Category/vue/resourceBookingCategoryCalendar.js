import { createApp } from 'vue';
import ResourceBookingCategoryCalendar from './resource-booking-category-calendar';

window.resourceBookingCategoryCalendar = createApp({
    components: {
        ResourceBookingCategoryCalendar
    }
}).mount('#calendar');

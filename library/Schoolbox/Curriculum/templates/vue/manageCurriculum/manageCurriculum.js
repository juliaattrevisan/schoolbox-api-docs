import { createApp } from 'vue';
import ManageCurriculum from './manage-curriculum';
import { createPinia } from 'pinia';
createApp({
    components: {
        ManageCurriculum
    }
}).use(createPinia()).mount('#manage-curriculum-container');
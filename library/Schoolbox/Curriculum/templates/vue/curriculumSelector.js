import { createApp } from 'vue';
import CurriculumSelector from './curriculum-selector';
import { createPinia } from 'pinia';
createApp({
    components: {
        CurriculumSelector
    }
}).use(createPinia()).mount('#curriculum-selector-container');


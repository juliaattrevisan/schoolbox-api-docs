import { createApp } from 'vue';
import { createPinia } from 'pinia'
import AdminList from './admin-list';

createApp({
	components: {
		AdminList
    }
}).use(createPinia()).mount('#admin-list');

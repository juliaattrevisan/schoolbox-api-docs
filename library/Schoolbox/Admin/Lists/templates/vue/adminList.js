import { createApp } from 'vue';
import store from './store/adminListStore'
import AdminList from './admin-list';

createApp({
	components: {
		AdminList
    }
}).use(store).mount('#admin-list');
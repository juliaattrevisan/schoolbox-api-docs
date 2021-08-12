import Vue from 'vue';
import store from './store/adminListStore'
import AdminList from './admin-list';

new Vue({
	el: '#admin-list',
    store,
	components: {
		AdminList
    }
});
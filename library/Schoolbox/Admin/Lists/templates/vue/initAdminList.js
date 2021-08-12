import Vue from 'vue';
import store from './adminListStore'
import AdminList from './admin-list';

new Vue({
	el: '#admin-list',
    store,
	components: {
		AdminList
    }
});
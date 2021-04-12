import Vue from 'vue';
import StaleUsersReport from './stale-users-report';
import {ClientTable} from 'vue-tables-2';

Vue.use(ClientTable);

new Vue({
	el: '#stale-users-report',
	components: {
		'stale-users-report': StaleUsersReport
    }
});

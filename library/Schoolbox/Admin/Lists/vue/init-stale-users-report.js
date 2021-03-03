import Vue from 'vue';
import StaleUsersReport from './stale-users-report';

new Vue({
	el: '#stale-users-report',
	components: {
		'stale-users-report': StaleUsersReport
    }
});

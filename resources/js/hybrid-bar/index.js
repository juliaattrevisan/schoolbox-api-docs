import Vue from 'vue';

require('../bootstrap');

Vue.component('search-bar-component', require('./components/SearchBarComponent').default);

new Vue({
    el: '#hybrid-bar'
});

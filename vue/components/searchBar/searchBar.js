import Vue from 'vue';

require('../../../resources/js/bootstrap');
require('./click-outside');

Vue.component('search-bar', require('./components/search-bar').default);

new Vue({
    el: '#hybrid-bar'
});

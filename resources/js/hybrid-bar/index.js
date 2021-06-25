import Vue from 'vue';

require('../bootstrap');
require('../click-outside');

Vue.component('search-bar', require('./bundle/search-bar').default);

new Vue({
    el: '#hybrid-bar'
});

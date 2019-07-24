import Vue from 'vue';

Vue.component('search-bar-component', require('./components/SearchBarComponent').default);

new Vue({
    el: '#hybrid-bar'
});

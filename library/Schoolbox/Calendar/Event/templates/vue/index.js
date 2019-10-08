import Vue from 'vue';

Vue.component('event-details', require('./event').default);

new Vue({
    el: '#event-details'
});

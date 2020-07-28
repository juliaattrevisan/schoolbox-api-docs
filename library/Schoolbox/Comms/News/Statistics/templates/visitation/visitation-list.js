import Vue from 'vue';

Vue.component('visitation-list', require('./visitation-list.vue').default);

new Vue({
    el: '#visitation-list-container'
});

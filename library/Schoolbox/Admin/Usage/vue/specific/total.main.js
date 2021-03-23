import Vue from 'vue';

Vue.component('usageSpecificTotal', require('./total.vue').default);

new Vue({
    el: '#usage-specific-total-container'
});
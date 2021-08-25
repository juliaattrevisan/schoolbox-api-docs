import Vue from 'vue';

Vue.component('usageGlobalTotal', require('./total.vue').default);

new Vue({
    el: '#usage-global-total-container'
});
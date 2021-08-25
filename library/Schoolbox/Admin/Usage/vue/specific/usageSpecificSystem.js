import Vue from 'vue';

Vue.component('usageSpecificSystem', require('./system.vue').default);

new Vue({
    el: '#usage-specific-system-container'
});
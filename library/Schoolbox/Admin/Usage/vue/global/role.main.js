import Vue from 'vue';

Vue.component('usageGlobalRole', require('./role.vue').default);

new Vue({
    el: '#usage-global-role-container'
});
import Vue from 'vue';

Vue.component('usageGlobalUser', require('./user.vue').default);

new Vue({
    el: '#usage-global-user-container'
});
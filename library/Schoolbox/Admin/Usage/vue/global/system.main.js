import Vue from 'vue';

Vue.component('usageGlobalSystem', require('./system.vue').default);

new Vue({
    el: '#usage-global-system-container'
});
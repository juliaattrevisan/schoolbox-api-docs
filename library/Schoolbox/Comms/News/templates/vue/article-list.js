import Vue from 'vue';

Vue.component('article-list', require('./article-list.vue').default);

new Vue({
    el: '#news-component'
});

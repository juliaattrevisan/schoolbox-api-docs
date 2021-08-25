import Vue from "vue";
import BadgeList from './badge-list';

Vue.component('badge-list', BadgeList);
window.badgeList = new Vue({
    el: '#badge-list-container'
});

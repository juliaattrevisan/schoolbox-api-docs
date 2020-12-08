import Vue from "vue";
import BadgeList from './badgeList';

Vue.component('badge-list', BadgeList);
window.badgeList = new Vue({
    el: '#badge-list-container'
});

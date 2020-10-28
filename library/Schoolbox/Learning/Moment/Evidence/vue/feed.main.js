import Vue from "vue";
import Feed from './feed.vue';

// This Vue instance uses the CKEditor plugin
import CKEditor from 'ckeditor4-vue';
Vue.use(CKEditor);

new Vue({
    el: '#activity-feed',
    render: function (createElement) {
        return createElement(Feed)
    }
});

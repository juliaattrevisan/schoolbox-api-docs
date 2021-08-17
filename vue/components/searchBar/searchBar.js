import Vue from 'vue';
import SearchBar from './search-bar';

require('./click-outside');

new Vue({
    el: '#hybrid-bar',
    components: {
        SearchBar
    }
});

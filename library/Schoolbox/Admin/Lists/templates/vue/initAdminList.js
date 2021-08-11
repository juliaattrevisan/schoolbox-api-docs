import Vue from 'vue';
import Vuex from 'vuex'
import AdminList from './admin-list';

Vue.use(Vuex);

const store = new Vuex.Store({

    state: {
        data: null, //contains the loaded data
        selectedRow: {}, //to track which row is selected
        isPopOutOpen: false, //to open and close the list
    },

    getters: {
        headerFields: (state) => {
            return state.data.fields.filter((field) => field.hasOwnProperty('width')); //use width to determine if it's to display in the header
        }
    },

    mutations: {
        setData (state, data) {
            state.data = data;
        },
        openPopUp (state, row) {
            state.selectedRow = row;
            state.isPopOutOpen = true;
        },
        closePopUp (state) {
            state.isPopOutOpen = false;
        }
    },
    
});

new Vue({
	el: '#admin-list',
    store,
	components: {
		AdminList
    }
});
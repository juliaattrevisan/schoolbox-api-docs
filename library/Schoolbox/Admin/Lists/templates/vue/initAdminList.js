import Vue from 'vue';
import Vuex from 'vuex'
import AdminList from './admin-list';

Vue.use(Vuex);

const store = new Vuex.Store({

    state: {
        data: null, //contains the loaded data
        selectedRow: {}, //to track which row is selected
        isPopOutOpen: false, //to open and close the list
        selectedPopOutName: '', //to highlight all other rows that have that permission
    },

    getters: {

        /**
         * Retrieve only those fields that will appear in the header
         * 
         * @returns { Array of Objects }
         */
        headerFields: (state) => {
            return state.data.fields.filter((field) => field.hasOwnProperty('width')); //use width to determine if it's to display in the header
        },

        /**
         * the popOut field that's used in the header, and is the reference for 
         * the following popOutFields
         * 
         * @returns { Object }
         */
        popOutHeaderField: (state, getters) => {
            const popOutHeaderField = getters.headerFields.find((field) => field.isPopOutList); //use width to determine if it's to display in the header
            if (typeof popOutHeaderField !== 'undefined') {
                return popOutHeaderField;
            } 
            return {};
        }, 
        
        /**
         * Just those fields that are used in a popoutList
         * 
         * @returns { Array of Objects }
         */
        popOutFields: (state) => {
            return state.data.fields.filter((field) => field.popOutList);
        }, 

    },

    mutations: {

        /**
         * 
         */
        setData (state, data) {
            state.data = data;
        },

        /**
         * To highlight all rows which also have the same pop out option
         * for an easier visual way to check which rows have the same pop out option
         */
        onPopOutNameSelected (state, popOutName) {
            if (state.selectedPopOutName === popOutName) { //so can toggle on/off
                state.selectedPopOutName = '';
            } else {
                state.selectedPopOutName = popOutName;
            }
        },

        /**
         * Display the options within the pop out
         */
        openPopOut (state, row) {
            state.selectedRow = row;
            state.isPopOutOpen = true;
            state.selectedPopOutName = '';
        },

        /**
         * Close the pop out
         */
        closePopOut (state) {
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
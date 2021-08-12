
import Vue from 'vue';
import Vuex from 'vuex'
import { UPDATE_LOADED_DATA, UPDATE_SELECTED_ROW, ON_DELETE_ROW, ON_ROW_DELETED } from './adminListMutationTypes';

Vue.use(Vuex);

export default new Vuex.Store({

    state: {
        loadedData: null, //contains the loaded data
        rows: [], //to display and possibly edit the rows 
        selectedRow: {}, //to track which row is selected
        rowToDelete: {}, //to track which row could be deleted
        isPopOutOpen: false, //to open and close the list
        selectedPopOutName: '', //to highlight all other rows that have that permission
        saveStatus: 'Save', //to update the button text when saving
        isProcessing: false, //to prevent multiple rapid-fire submissions
    },

    getters: {

        /**
         * Retrieve only those fields that will appear in the header
         * 
         * @returns { Array of Objects }
         */
        headerFields: (state) => {
            return state.loadedData.fields.filter((field) => field.hasOwnProperty('width')); //use width to determine if it's to display in the header
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
            return state.loadedData.fields.filter((field) => field.popOutList);
        }, 

    },

    mutations: {

        /**
         * 
         */
        [UPDATE_LOADED_DATA] (state, loadedData) {
            state.loadedData = loadedData;
            state.rows = _.cloneDeep(state.loadedData.rows); //cloneDeep is from underscore.js
            state.rows.forEach((row) => row.isNew = false); //Add a boolean to know whether the row is one being newly added
        },

        /**
         * 
         */
        [UPDATE_SELECTED_ROW] (state, row) {
            state.selectedRow = row;
            state.selectedPopOutName = '';
        },

        /**
         * 
         */
        addRow (state, row) {
            state.rows = [...state.rows, row];
        },

        /**
         * 
         */
        [ON_DELETE_ROW] (state, rowToDelete) {
            state.rowToDelete = rowToDelete;
        },

        /**
         * Now the row is deleted, remove it from the rows list
         */
        [ON_ROW_DELETED] (state, rowToDelete) {
            state.rows = state.rows.filter((row) => row.id !== rowToDelete.id);
        },

        /**
         * 
         */
        setSaveStatus (state, saveStatus) {
            state.saveStatus = saveStatus;
        },

        /**
         * 
         */
        setIsProcessing (state, isProcessing) {
            state.isProcessing = isProcessing;
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